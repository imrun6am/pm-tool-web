<?php

namespace App\Services;

use App\Jobs\ExtractTriageRequests;
use App\Models\Label;
use App\Models\Task;
use App\Models\TriageImport;
use App\Models\TriageItem;
use App\Models\User;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\RequestExtractor;
use App\Services\Calendar\DocumentTextConverter;
use App\Services\Calendar\EventImportService;
use App\Services\Calendar\TextChunker;
use App\Services\Calendar\UnreadableDocumentException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Create requests from a file, screenshot or text": upload → queued reading →
 * review → confirm. The same shape as calendar import, sharing its readers.
 *
 * The rules worth centralising:
 *  - whatever the AI returns is cleaned before it is stored, and a team or type
 *    is kept only if it names one the sender may choose;
 *  - nothing reaches `triage_items` before confirm(), which goes through
 *    TriageService::submit so permissions and notices match the request form;
 *  - a proposal that became a request is never sent again.
 */
class TriageImportService
{
    /** Keeps the review form well inside PHP's default max_input_vars. */
    public const MAX_PROPOSALS = 100;

    /** Calendar import's types, without its calendar format. */
    public const EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'docx', 'xlsx', 'csv', 'txt', 'md'];

    public const MAX_FILE_KB = 20480;

    /** The model's per-image limit. */
    public const MAX_IMAGE_KB = 5120;

    public function __construct(
        private readonly TriageService $triage,
        private readonly RequestExtractor $extractor,
        private readonly DocumentTextConverter $converter,
        private readonly AiFeatures $features,
        private readonly NotificationService $notifications,
    ) {}

    /** Whether a new import or a retry can run right now. */
    public function available(): bool
    {
        return $this->features->available(RequestExtractor::FEATURE);
    }

    /**
     * What still needs this person: running, waiting for review, or failed.
     *
     * @return Collection<int, TriageImport>
     */
    public function open(User $user): Collection
    {
        return TriageImport::where('user_id', $user->id)
            ->where('status', '!=', TriageImport::STATUS_COMPLETED)
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /** @return Collection<int, TriageImport> */
    public function history(User $user): Collection
    {
        return TriageImport::where('user_id', $user->id)->latest('id')->limit(50)->get();
    }

    /** Store the upload privately, record it and queue the reading. Returns at once. */
    public function start(User $user, UploadedFile $file): TriageImport
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $import = TriageImport::create([
            'user_id' => $user->id,
            'input' => TriageImport::INPUT_FILE,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_path' => $file->storeAs("triage-imports/{$user->id}", Str::uuid().'.'.$extension, 'local'),
            'extension' => $extension,
            'mime_type' => EventImportService::NATIVE[$extension] ?? $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ]);

        ExtractTriageRequests::dispatch($import->id);

        return $import;
    }

    /** Written to the private disk like an upload, so the job, retry and delete treat it the same. */
    public function startFromText(User $user, string $pasted): TriageImport
    {
        $text = EventImportService::pastedText($pasted);
        $path = "triage-imports/{$user->id}/".Str::uuid().'.txt';
        Storage::disk('local')->put($path, $text);

        $import = TriageImport::create([
            'user_id' => $user->id,
            'input' => TriageImport::INPUT_PASTE,
            'original_name' => 'pasted-text.txt',
            'preview' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', $text)), 0, 160, '…'),
            'file_path' => $path,
            'extension' => 'txt',
            'mime_type' => 'text/plain',
            'size' => mb_strlen($text),
        ]);

        ExtractTriageRequests::dispatch($import->id);

        return $import;
    }

    /** Re-queue a failed import; finished parts are kept, so it resumes. */
    public function retry(TriageImport $import): void
    {
        $import->update(['status' => TriageImport::STATUS_PENDING, 'error' => null]);

        ExtractTriageRequests::dispatch($import->id);
    }

    /** The file goes too. Requests already sent stay and lose their link (the foreign key clears it). */
    public function delete(TriageImport $import): void
    {
        Storage::disk('local')->delete($import->file_path);
        $import->delete();
    }

    /**
     * The job's work: read the source, collect raw proposals, store them cleaned
     * and hand the import over for review. Text saves its progress after every
     * part, so a retry skips finished parts.
     */
    public function process(TriageImport $import): void
    {
        $import->update(['status' => TriageImport::STATUS_PROCESSING, 'error' => null]);

        $path = Storage::disk('local')->path($import->file_path);
        if (! is_file($path)) {
            throw new UnreadableDocumentException('The uploaded file is missing. Upload it again.');
        }

        $user = $import->user;
        $context = [
            'teams' => $this->triage->teamsFor($user)->pluck('name')->all(),
            'types' => Label::orderBy('name')->pluck('name')->all(),
        ];

        if (isset(EventImportService::NATIVE[$import->extension])) {
            $import->update(['chunks_total' => 1, 'chunks_done' => 0]);
            $raw = $this->extractor->readFile($path, EventImportService::NATIVE[$import->extension], $import->original_name, $context, $user);
            $import->update(['chunks_done' => 1]);
            $this->finish($import, $raw, $user);

            return;
        }

        $chunks = TextChunker::split($this->converter->convert($path, $import->extension));
        $resume = $import->chunks_total === count($chunks) && $import->chunks_done > 0;
        $raw = $resume ? $import->proposalList() : [];
        $import->update(['chunks_total' => count($chunks), 'chunks_done' => $resume ? $import->chunks_done : 0]);

        foreach ($chunks as $i => $chunk) {
            if ($i < $import->chunks_done) {
                continue;
            }

            $raw = array_merge($raw, $this->extractor->readText($chunk, $i + 1, count($chunks), $import->original_name, $context, $user));
            $import->update(['chunks_done' => $i + 1, 'proposals' => $raw]);
        }

        $this->finish($import, $raw, $user);
    }

    /**
     * The proposals as review rows, each flagged when this person already has a
     * waiting request with that title (so a re-import isn't sent twice).
     *
     * @return list<array<string, mixed>>
     */
    public function reviewRows(TriageImport $import): array
    {
        $waiting = TriageItem::where('reporter_id', $import->user_id)
            ->where('status', TriageItem::PENDING)
            ->pluck('title')
            ->map(fn (string $title) => mb_strtolower(trim($title)))
            ->flip();

        return array_map(fn (array $row) => $row + [
            'already_sent' => $row['triage_item_id'] === null && $waiting->has(mb_strtolower($row['title'])),
        ], $import->proposalList());
    }

    /**
     * Send the ticked rows as requests (edited values win over the stored
     * proposal) and mark the import completed. Rows already sent are skipped.
     *
     * @param  array<int|string, array<string, mixed>>  $rows  validated review rows keyed by proposal index
     * @return Collection<int, TriageItem>
     */
    public function confirm(TriageImport $import, array $rows, User $user): Collection
    {
        // Outside the transaction: a decider gets one grouped row, and a rollback sends nothing.
        return $this->notifications->batch(fn () => DB::transaction(function () use ($import, $rows, $user) {
            $import = TriageImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $proposals = $import->proposalList();
            $created = collect();

            foreach ($rows as $index => $row) {
                if (empty($row['include']) || ! isset($proposals[$index]) || ! empty($proposals[$index]['triage_item_id'])) {
                    continue;
                }

                $fields = [
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'team_id' => $row['team_id'] ?? null,
                    'priority' => $row['priority'] ?? null,
                    'label_id' => $row['label_id'] ?? null,
                ];

                $item = $this->triage->submit($user, $fields + ['triage_import_id' => $import->id]);

                $proposals[$index] = array_merge($proposals[$index], $fields, ['triage_item_id' => $item->id]);
                $created->push($item);
            }

            if ($created->isNotEmpty()) {
                $import->update([
                    'proposals' => $proposals,
                    'status' => TriageImport::STATUS_COMPLETED,
                    'requests_created' => $import->requests_created + $created->count(),
                    'confirmed_at' => now(),
                ]);
            }

            return $created;
        }));
    }

    /**
     * Clean raw output into the stored proposal shape, keeping document order.
     * Team and type must name something this sender may choose.
     *
     * @param  list<mixed>  $raw
     * @return array{rows: list<array<string, mixed>>, dropped: int}
     */
    public function normalize(array $raw, User $user): array
    {
        $teams = $this->triage->teamsFor($user);
        $teamIds = $teams->mapWithKeys(fn ($t) => [mb_strtolower(trim($t->name)) => $t->id]);
        $labelIds = Label::get(['id', 'name'])->mapWithKeys(fn ($l) => [mb_strtolower(trim($l->name)) => $l->id]);
        // Someone with one team doesn't choose, as on the request form.
        $onlyTeam = $user->hasLimitedAccess() && $teams->count() === 1 ? $teams->first()->id : null;

        $byTitle = [];
        foreach ($raw as $item) {
            $title = is_array($item) ? mb_substr(trim(preg_replace('/\s+/', ' ', self::clean($item['title'] ?? ''))), 0, 255) : '';
            if ($title === '') {
                continue;
            }

            $confidence = round(min(1, max(0, is_numeric($item['confidence'] ?? null) ? (float) $item['confidence'] : 0.5)), 2);
            $key = mb_strtolower($title);

            // The same request seen twice (e.g. on a part boundary): keep the surer copy, in the first one's place.
            if (isset($byTitle[$key]) && $byTitle[$key]['confidence'] >= $confidence) {
                continue;
            }

            $description = mb_substr(trim(self::clean($item['description'] ?? '')), 0, 5000);

            $byTitle[$key] = [
                'title' => $title,
                'description' => $description === '' ? null : $description,
                'team_id' => $onlyTeam ?? $teamIds->get(mb_strtolower(trim(self::clean($item['team'] ?? '')))),
                'priority' => array_key_exists($item['priority'] ?? '', Task::PRIORITIES) ? $item['priority'] : null,
                'label_id' => $labelIds->get(mb_strtolower(trim(self::clean($item['type'] ?? '')))),
                'confidence' => $confidence,
                'triage_item_id' => null,
            ];
        }

        return [
            'rows' => array_slice(array_values($byTitle), 0, self::MAX_PROPOSALS),
            'dropped' => max(0, count($byTitle) - self::MAX_PROPOSALS),
        ];
    }

    private function finish(TriageImport $import, array $raw, User $user): void
    {
        ['rows' => $rows, 'dropped' => $dropped] = $this->normalize($raw, $user);

        $import->update([
            'status' => TriageImport::STATUS_NEEDS_REVIEW,
            'proposals' => $rows,
            'proposals_dropped' => $dropped,
            'processed_at' => now(),
            'error' => null,
        ]);
    }

    /** Text with control characters removed, keeping line breaks. Anything that isn't text becomes empty. */
    private static function clean(mixed $value): string
    {
        return is_string($value) ? (string) preg_replace('/[^\P{C}\n\t]/u', '', $value) : '';
    }
}

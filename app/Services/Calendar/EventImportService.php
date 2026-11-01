<?php

namespace App\Services\Calendar;

use App\Jobs\ExtractCalendarEvents;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\Release;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Create events from a file": upload → queued extraction → review → confirm.
 *
 * The rules worth centralising:
 *  - routing by format: .ics is parsed deterministically, PDFs and images go
 *    to the extractor whole, everything else becomes text split into chunks;
 *  - normalization: whatever the extractor returns is clamped, validated and
 *    de-duplicated before it is stored, so the review form never sees junk;
 *  - matching: attendee names/emails → users and release hints → releases;
 *  - nothing reaches `events` before confirm(), and a proposal that became an
 *    event is never created again.
 */
class EventImportService
{
    public const MAX_PROPOSALS = 300;

    public const MAX_ATTENDEES = 50;

    /** extension => media type for the files the extractor reads natively */
    public const NATIVE = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public const EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ics', 'docx', 'xlsx', 'csv', 'txt', 'md'];

    /** ICS files are read whole; anything larger is not a real calendar export. */
    private const MAX_ICS_BYTES = 10485760;

    public function __construct(
        private readonly EventService $events,
        private readonly DocumentTextConverter $converter,
        private readonly IcsParser $ics,
    ) {}

    /** Store the upload privately, record it and queue the extraction. Returns at once. */
    public function start(User $user, UploadedFile $file, ?string $timezone): EventImport
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $import = EventImport::create([
            'user_id' => $user->id,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_path' => $file->storeAs("event-imports/{$user->id}", Str::uuid().'.'.$extension, 'local'),
            'extension' => $extension,
            'mime_type' => self::NATIVE[$extension] ?? $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'timezone' => $timezone ?: config('app.timezone'),
            'status' => EventImport::STATUS_PENDING,
        ]);

        ExtractCalendarEvents::dispatch($import->id);

        return $import;
    }

    /**
     * Import pasted text (an email, chat message, agenda, or iCalendar data).
     * It is written to the private disk like an upload, so the queued job,
     * chunking, ICS parser, retry and delete treat it exactly like a file.
     */
    public function startFromText(User $user, string $pasted, ?string $timezone): EventImport
    {
        $isIcs = self::isIcs($pasted);
        $text = $isIcs ? str_replace(["\r\n", "\r"], "\n", trim($pasted)) : self::pastedText($pasted);
        $extension = $isIcs ? 'ics' : 'txt';

        $path = "event-imports/{$user->id}/".Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $text);

        $import = EventImport::create([
            'user_id' => $user->id,
            'input' => EventImport::INPUT_PASTE,
            'original_name' => $isIcs ? 'pasted-calendar.ics' : 'pasted-text.txt',
            'preview' => self::preview($text, $isIcs),
            'file_path' => $path,
            'extension' => $extension,
            'mime_type' => $isIcs ? 'text/calendar' : 'text/plain',
            'size' => mb_strlen($text),
            'timezone' => $timezone ?: config('app.timezone'),
            'status' => EventImport::STATUS_PENDING,
        ]);

        ExtractCalendarEvents::dispatch($import->id);

        return $import;
    }

    public static function isIcs(string $text): bool
    {
        return (bool) preg_match('/^\s*BEGIN:VCALENDAR\b/i', $text);
    }

    /**
     * Pasted content as plain text. A paste of HTML (an email's source, rich
     * text that arrived with its markup) keeps its line structure; table
     * cells become " | " so a schedule table still reads row by row.
     */
    public static function pastedText(string $pasted): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $pasted);

        if (preg_match('#<(html|body|div|p|br|span|table|tr|td|li|ul|ol|h[1-6]|a|b|strong|font)\b[^>]*>#i', $text)) {
            $text = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $text);
            $text = preg_replace('#</t[dh]>#i', ' | ', $text);
            $text = preg_replace('#<br\s*/?>|</(div|p|li|tr|h[1-6]|blockquote|pre|table)>#i', "\n", $text);
            $text = preg_replace('#<li[^>]*>#i', '- ', $text);
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/[ \t]*\|[ \t]*\n/', "\n", $text);
        }

        return trim(preg_replace(["/[ \t]+\n/", "/\n{3,}/"], ["\n", "\n\n"], $text));
    }

    /** One line for the history list: the opening words, or the event titles of pasted iCalendar data. */
    private static function preview(string $text, bool $isIcs): string
    {
        if ($isIcs) {
            preg_match_all('/^SUMMARY[^:]*:(.+)$/mi', $text, $m);
            $titles = array_map(fn ($t) => trim(str_replace(['\\,', '\\;'], [',', ';'], $t)), $m[1]);
            $line = $titles === [] ? 'Calendar data' : 'Calendar: '.implode(', ', $titles);
        } else {
            $line = preg_replace('/\s+/', ' ', $text);
        }

        return mb_strimwidth(trim($line), 0, 160, '…');
    }

    /** Re-queue a failed import; finished chunks are kept, so it resumes. */
    public function retry(EventImport $import): void
    {
        $import->update(['status' => EventImport::STATUS_PENDING, 'error' => null]);

        ExtractCalendarEvents::dispatch($import->id);
    }

    public function delete(EventImport $import): void
    {
        Storage::disk('local')->delete($import->file_path);
        $import->delete();
    }

    /**
     * The job's work: read the file, collect raw events, store normalized
     * proposals and hand the import over for review. Text documents save
     * their progress after every chunk, so a retry skips finished chunks.
     */
    public function process(EventImport $import, EventExtractor $extractor): void
    {
        $import->update(['status' => EventImport::STATUS_PROCESSING, 'error' => null]);

        $path = Storage::disk('local')->path($import->file_path);
        if (! is_file($path)) {
            throw new UnreadableDocumentException('The uploaded file is missing. Upload it again.');
        }

        if ($import->extension === 'ics') {
            if (filesize($path) > self::MAX_ICS_BYTES) {
                throw new UnreadableDocumentException('This calendar file is too large to import.');
            }
            $events = $this->ics->parse((string) file_get_contents($path), $import->timezone);
            if ($events !== []) {
                $import->update(['source' => EventImport::SOURCE_ICS, 'chunks_total' => 1, 'chunks_done' => 1]);
                $this->finish($import, $events);

                return;
            }
            // No VEVENT: read it like any other text document.
        }

        $context = $this->context($import);
        $import->update(['source' => EventImport::SOURCE_CLAUDE]);

        if (isset(self::NATIVE[$import->extension])) {
            $import->update(['chunks_total' => 1, 'chunks_done' => 0]);
            $result = $extractor->extract([
                'kind' => 'file', 'path' => $path, 'mime' => self::NATIVE[$import->extension], 'name' => $import->original_name,
            ], $context);
            $this->recordUsage($import, $result['usage'], ['chunks_done' => 1]);
            $this->finish($import, $result['events']);

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

            $result = $extractor->extract([
                'kind' => 'text', 'text' => $chunk, 'part' => $i + 1, 'parts' => count($chunks), 'name' => $import->original_name,
            ], $context);

            $raw = array_merge($raw, $result['events']);
            $this->recordUsage($import, $result['usage'], ['chunks_done' => $i + 1, 'proposals' => $raw]);
        }

        $this->finish($import, $raw);
    }

    /**
     * The proposals as review rows, each flagged when an event with the same
     * title already exists on that day (so a re-imported invite isn't doubled).
     *
     * @return list<array<string, mixed>>
     */
    public function reviewRows(EventImport $import): array
    {
        $rows = $import->proposalList();
        $dates = collect($rows)->pluck('date')->filter()->sort()->values();
        if ($dates->isEmpty()) {
            return $rows;
        }

        $existing = Event::query()
            ->whereDate('starts_at', '>=', $dates->first())
            ->whereDate('starts_at', '<=', $dates->last())
            ->get(['id', 'title', 'starts_at'])
            ->groupBy(fn (Event $e) => $e->starts_at->toDateString().'|'.mb_strtolower(trim($e->title)));

        $created = collect($rows)->pluck('event_id')->filter()->all();

        return array_map(function (array $row) use ($existing, $created) {
            $match = $existing->get($row['date'].'|'.mb_strtolower($row['title']))?->first(fn (Event $e) => ! in_array($e->id, $created, true));
            $row['duplicate_of'] = $row['event_id'] ? null : $match?->id;

            return $row;
        }, $rows);
    }

    /**
     * Create the included rows as events (edited values win over the stored
     * proposal) and mark the import completed. Rows that already became
     * events are skipped. Returns the events created, earliest first.
     *
     * @param  array<int|string, array<string, mixed>>  $rows  validated review rows keyed by proposal index
     * @return Collection<int, Event>
     */
    public function confirm(EventImport $import, array $rows, User $user): Collection
    {
        return DB::transaction(function () use ($import, $rows, $user) {
            $import = EventImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $proposals = $import->proposalList();
            $created = collect();

            foreach ($rows as $index => $row) {
                if (empty($row['include']) || ! isset($proposals[$index]) || ! empty($proposals[$index]['event_id'])) {
                    continue;
                }

                [$start, $end] = self::window($row);
                $allDay = self::isAllDay($row);

                $event = $this->events->create([
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'visibility' => $row['visibility'] ?? Event::VISIBILITY_SHARED,
                    'description' => $row['description'] ?? null,
                    'location' => $row['location'] ?? null,
                    'release_id' => $row['release_id'] ?? null,
                    'all_day' => $allDay,
                    'starts_at' => $start->format('Y-m-d H:i:s'),
                    'ends_at' => $end?->format('Y-m-d H:i:s'),
                ], array_values(array_unique(array_map('intval', $row['attendees'] ?? []))), $user);

                $proposals[$index] = array_merge($proposals[$index], [
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'visibility' => $row['visibility'] ?? Event::VISIBILITY_SHARED,
                    'date' => $row['date'],
                    'start_time' => $allDay ? null : ($row['start_time'] ?? null),
                    'end_date' => $row['end_date'] ?? null,
                    'end_time' => $allDay ? null : ($row['end_time'] ?? null),
                    'all_day' => $allDay,
                    'description' => $row['description'] ?? null,
                    'location' => $row['location'] ?? null,
                    'release_id' => $row['release_id'] ?? null,
                    'attendee_ids' => $event->attendees()->pluck('users.id')->all(),
                    'event_id' => $event->id,
                ]);
                $created->push($event);
            }

            if ($created->isNotEmpty()) {
                $import->update([
                    'proposals' => $proposals,
                    'status' => EventImport::STATUS_COMPLETED,
                    'events_created' => $import->events_created + $created->count(),
                    'confirmed_at' => now(),
                ]);
            }

            return $created->sortBy('starts_at')->values();
        });
    }

    /** A row is all-day when flagged so or when it has no start time. */
    public static function isAllDay(array $row): bool
    {
        return ! empty($row['all_day']) || blank($row['start_time'] ?? null);
    }

    /**
     * The start and (optional) end a review row describes. An end date
     * without a time keeps the start's time; an end time alone is the same day.
     *
     * @return array{0: Carbon, 1: ?Carbon}
     */
    public static function window(array $row): array
    {
        $allDay = self::isAllDay($row);
        $start = Carbon::parse($row['date'].' '.($allDay ? '00:00' : $row['start_time']));

        $endDate = filled($row['end_date'] ?? null) ? $row['end_date'] : null;
        $endTime = ! $allDay && filled($row['end_time'] ?? null) ? $row['end_time'] : null;
        if ($endDate === null && $endTime === null) {
            return [$start, null];
        }

        $end = Carbon::parse(($endDate ?? $row['date']).' '.($allDay ? '00:00' : ($endTime ?? $row['start_time'])));

        return [$start, $end];
    }

    /**
     * Clamp, validate and de-duplicate raw extractor output into the stored
     * proposal shape, then match people and releases.
     *
     * @param  list<mixed>  $raw
     * @return list<array<string, mixed>>
     */
    public function normalize(array $raw): array
    {
        $byKey = [];

        foreach ($raw as $item) {
            if (! is_array($item) || ($p = $this->normalizeOne($item)) === null) {
                continue;
            }

            $key = mb_strtolower($p['title']).'|'.$p['date'].'|'.($p['start_time'] ?? '');
            if (isset($byKey[$key])) {
                // The same event seen twice (e.g. on a chunk boundary): keep the surer copy, union the people.
                $keep = $byKey[$key]['confidence'] >= $p['confidence'] ? $byKey[$key] : $p;
                $keep['attendees'] = array_values(array_unique([...$byKey[$key]['attendees'], ...$p['attendees']]));
                $byKey[$key] = $keep;
            } else {
                $byKey[$key] = $p;
            }
        }

        $proposals = collect($byKey)
            ->sortBy(fn (array $p) => $p['date'].' '.($p['start_time'] ?? '00:00'))
            ->take(self::MAX_PROPOSALS)
            ->values()
            ->all();

        return $this->match($proposals);
    }

    private function normalizeOne(array $item): ?array
    {
        $title = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($item['title'] ?? ''))), 0, 255);
        $date = self::date($item['start_date'] ?? null);
        if ($title === '' || $date === null) {
            return null;
        }

        $startTime = self::time($item['start_time'] ?? null);
        $allDay = (bool) ($item['all_day'] ?? false) || $startTime === null;
        $endDate = self::date($item['end_date'] ?? null);
        $endDate = $endDate !== null && $endDate > $date ? $endDate : null;
        $endTime = $allDay ? null : self::time($item['end_time'] ?? null);

        $p = [
            'title' => $title,
            'type' => array_key_exists($item['type'] ?? null, Event::TYPES) ? $item['type'] : 'other',
            // Like the event form: shared unless the reviewer makes it private.
            'visibility' => Event::VISIBILITY_SHARED,
            'date' => $date,
            'start_time' => $allDay ? null : $startTime,
            'end_date' => $endDate,
            'end_time' => $endTime,
            'all_day' => $allDay,
            'description' => self::text($item['description'] ?? null, 5000),
            'location' => self::text($item['location'] ?? null, 255),
            'attendees' => collect(is_array($item['attendees'] ?? null) ? $item['attendees'] : [])
                ->filter(fn ($a) => is_string($a) && trim($a) !== '')
                ->map(fn (string $a) => mb_substr(trim($a), 0, 255))
                ->unique(fn (string $a) => mb_strtolower($a))
                ->take(self::MAX_ATTENDEES)
                ->values()
                ->all(),
            'release_hint' => self::text($item['release_hint'] ?? null, 255),
            'confidence' => round(min(1, max(0, is_numeric($item['confidence'] ?? null) ? (float) $item['confidence'] : 0.5)), 2),
        ];

        // An end before the start is a misread; drop it rather than the event.
        [$start, $end] = self::window($p);
        if ($end !== null && $end->lt($start)) {
            $p['end_date'] = null;
            $p['end_time'] = null;
        }

        return $p;
    }

    /**
     * @param  list<array<string, mixed>>  $proposals
     * @return list<array<string, mixed>>
     */
    private function match(array $proposals): array
    {
        $users = User::active()->get(['id', 'name', 'email']);
        $releases = Release::orderByDesc('year')->get(['id', 'name']);

        return array_map(function (array $p) use ($users, $releases) {
            $ids = [];
            $unmatched = [];
            foreach ($p['attendees'] as $attendee) {
                ($id = $this->matchUser($attendee, $users)) !== null ? $ids[] = $id : $unmatched[] = $attendee;
            }

            return $p + [
                'attendee_ids' => array_values(array_unique($ids)),
                'unmatched_attendees' => $unmatched,
                'release_id' => $this->matchRelease($p['release_hint'], $releases),
                'event_id' => null,
            ];
        }, $proposals);
    }

    /** By email, then full name, then a first name only one user has. */
    private function matchUser(string $attendee, Collection $users): ?int
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $attendee, $m)) {
            $byEmail = $users->first(fn (User $u) => strcasecmp($u->email, $m[0]) === 0);
            if ($byEmail) {
                return $byEmail->id;
            }
        }

        $name = mb_strtolower(trim(preg_replace('/<[^>]*>|\([^)]*\)|["\']/', '', $attendee)));
        if ($name === '' || str_contains($name, '@')) {
            return null;
        }

        $byName = $users->first(fn (User $u) => mb_strtolower(trim($u->name)) === $name);
        if ($byName) {
            return $byName->id;
        }

        if (! str_contains($name, ' ')) {
            $byFirst = $users->filter(fn (User $u) => mb_strtolower(Str::before(trim($u->name), ' ')) === $name);
            if ($byFirst->count() === 1) {
                return $byFirst->first()->id;
            }
        }

        return null;
    }

    /** By exact name, then a unique partial match either way ("2.4" ↔ "Release 2.4"). */
    private function matchRelease(?string $hint, Collection $releases): ?int
    {
        $hint = mb_strtolower(trim((string) $hint));
        if ($hint === '') {
            return null;
        }

        $exact = $releases->first(fn (Release $r) => mb_strtolower(trim($r->name)) === $hint);
        if ($exact) {
            return $exact->id;
        }

        $partial = $releases->filter(function (Release $r) use ($hint) {
            $name = mb_strtolower(trim($r->name));

            return $name !== '' && (str_contains($name, $hint) || str_contains($hint, $name));
        });

        return $partial->count() === 1 ? $partial->first()->id : null;
    }

    private function finish(EventImport $import, array $raw): void
    {
        $import->update([
            'status' => EventImport::STATUS_NEEDS_REVIEW,
            'proposals' => $this->normalize($raw),
            'processed_at' => now(),
            'error' => null,
        ]);
    }

    /** @return array{today: string, timezone: string, releases: list<string>} */
    private function context(EventImport $import): array
    {
        return [
            'today' => now($import->timezone)->format('l, Y-m-d'),
            'timezone' => $import->timezone,
            'releases' => Release::orderByDesc('year')->limit(100)->pluck('name')->filter()->values()->all(),
        ];
    }

    /** @param  array{input_tokens: int, output_tokens: int}  $usage */
    private function recordUsage(EventImport $import, array $usage, array $attributes): void
    {
        $import->update($attributes + [
            'input_tokens' => $import->input_tokens + $usage['input_tokens'],
            'output_tokens' => $import->output_tokens + $usage['output_tokens'],
        ]);
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', trim($value)));

        return checkdate($m, $d, $y) && $y >= 1970 && $y <= 2100 ? trim($value) : null;
    }

    private static function time(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:\d{2})?$/', trim($value), $m)) {
            return null;
        }

        return str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2];
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $max);
    }
}

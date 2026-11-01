<?php

namespace App\Services;

use App\Jobs\MergeTasksheetExport;
use App\Jobs\RenderTasksheetExportChunk;
use App\Models\TasksheetEntry;
use App\Models\TasksheetExport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;

/**
 * Tasksheet PDFs built in the background, a bounded chunk at a time.
 *
 * The inline report rendered every row of a range into one HTML document and
 * let dompdf lay it all out in one request, which is what took workers down
 * on wide ranges. Here the export is planned as chunks of consecutive days
 * (at most `tasksheet.export.chunk_rows` rows each), every chunk is rendered
 * by its own queued job into a partial PDF, and a final job merges the parts
 * with FPDI. No job holds more than one chunk of rows.
 *
 * Who may export what is decided by TasksheetExportPolicy, not here. Rows and
 * the feedback rule come from TasksheetService, shared with the inline report.
 */
class TasksheetExportService
{
    /** The finished file, inside the export's directory. */
    public const FILE = 'tasksheet.pdf';

    public function __construct(private readonly TasksheetService $tasksheet) {}

    /**
     * The user's own exports, newest first.
     *
     * @return Collection<int, TasksheetExport>
     */
    public function recentFor(User $user, int $limit = 25): Collection
    {
        return TasksheetExport::with(['team', 'subject', 'requester'])
            ->where('requested_by', $user->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The given exports, restricted to the user's own: ids that belong to
     * someone else simply do not come back.
     *
     * @param  list<int>  $ids
     * @return Collection<int, TasksheetExport>
     */
    public function statusesFor(User $user, array $ids): Collection
    {
        return TasksheetExport::where('requested_by', $user->id)->whereIn('id', $ids)->get();
    }

    /**
     * Who the export form offers as its subject. A lead may pick any developer
     * or QA (and keeps a prefilled member who no longer fits, say one linked
     * from a KPI spreadsheet); anyone else only ever exports their own rows.
     *
     * @return Collection<int, User>
     */
    public function subjectsFor(User $viewer, ?int $include = null): Collection
    {
        if (! $viewer->isLead()) {
            return collect([$viewer]);
        }

        return User::withTrashed()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('deleted_at')->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA]))
                ->when($include, fn ($w, $id) => $w->orWhereKey($id)))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'deleted_at']);
    }

    /** Whether the user may start another export without flooding the shared worker. */
    public function hasCapacity(User $user): bool
    {
        return TasksheetExport::where('requested_by', $user->id)
            ->whereIn('status', [TasksheetExport::STATUS_PENDING, TasksheetExport::STATUS_PROCESSING])
            ->count() < max(1, (int) config('tasksheet.export.max_running', 3));
    }

    /**
     * Normalize export input — the export form, or the old report URL's query.
     * `member` is the export's subject here, not the daily sheet's row filter.
     *
     * @param  array<string, mixed>  $input
     * @return array{team_id: ?int, member_id: ?int, from: string, to: string, filters: array<string, string>}
     */
    public function selection(array $input): array
    {
        [$from, $to] = $this->tasksheet->reportRange($input);

        return [
            'team_id' => filled($input['team'] ?? null) ? (int) $input['team'] : null,
            'member_id' => filled($input['member'] ?? null) ? (int) $input['member'] : null,
            'from' => $from,
            'to' => $to,
            'filters' => array_filter(Arr::except(
                $this->tasksheet->parseFilters(Arr::only($input, ['attendance', 'fill', 'leave'])),
                'member',
            )),
        ];
    }

    /**
     * Record the export and queue it. Returns as soon as the batch is queued;
     * the only query run here is the per-day row count the plan needs.
     *
     * @param  array<string, mixed>  $input
     */
    public function start(User $requester, array $input): TasksheetExport
    {
        $selection = $this->selection($input);

        $export = TasksheetExport::create([
            'requested_by' => $requester->id,
            'team_id' => $selection['team_id'],
            'member_id' => $selection['member_id'],
            'date_from' => $selection['from'],
            'date_to' => $selection['to'],
            'filters' => $selection['filters'] ?: null,
            // Snapshot of what the requester was allowed when they asked; the
            // render and the download both re-check it against their role now.
            'include_feedback' => $this->tasksheet->includesFeedbackFor($requester),
        ]);

        return $this->queue($export);
    }

    /** Start a failed export again from scratch. */
    public function retry(TasksheetExport $export): TasksheetExport
    {
        $this->cancelBatch($export);
        Storage::disk('local')->deleteDirectory($export->directory());

        $export->update([
            'status' => TasksheetExport::STATUS_PENDING,
            'chunks_total' => 0, 'chunks_done' => 0, 'rows_total' => 0,
            'batch_id' => null, 'file_path' => null, 'error' => null,
            'started_at' => null, 'completed_at' => null,
        ]);

        return $this->queue($export);
    }

    /** Cancel (if running) and delete an export with all of its files. */
    public function delete(TasksheetExport $export): void
    {
        $this->cancelBatch($export);
        Storage::disk('local')->deleteDirectory($export->directory());
        $export->delete();
    }

    /**
     * Split the export into chunks.
     *
     * One `GROUP BY date` count, then consecutive days are packed until the
     * next would exceed the row budget. Chunks are date bounds rather than
     * offsets, so a row saved mid-export cannot shift another into a
     * neighbouring chunk, and bounds tile the whole range so even days that
     * were empty at planning time belong to exactly one chunk. A single day
     * over budget (an all-teams export, say) is split by member instead.
     *
     * @return array{rows: int, chunks: list<array{from: string, to: string, after: ?int, upTo: ?int, rows: int}>}
     */
    public function plan(TasksheetExport $export): array
    {
        $limit = max(1, (int) config('tasksheet.export.chunk_rows', 75));
        $from = $export->date_from->toDateString();
        $to = $export->date_to->toDateString();

        // Keyed by Y-m-d and summed, so a driver that stores the date cast as
        // a midnight timestamp cannot split one day into two groups.
        $perDay = $this->rowsQuery($export, $from, $to)
            ->selectRaw('date as day, count(*) as n')
            ->groupBy('date')
            ->toBase()
            ->get()
            ->groupBy(fn ($r) => Carbon::parse($r->day)->toDateString())
            ->map(fn (Collection $g) => (int) $g->sum('n'))
            ->sortKeys();

        // Pass 1: groups of whole days, or member slices of one oversized day.
        $groups = [];
        $open = null;
        foreach ($perDay as $day => $count) {
            if ($count > $limit) {
                if ($open) {
                    $groups[] = $open;
                    $open = null;
                }
                foreach ($this->memberSlices($export, $day, $limit) as $slice) {
                    $groups[] = ['start' => $day, 'slice' => true, 'rows' => 0] + $slice;
                }

                continue;
            }

            if ($open && $limit < $open['rows'] + $count) {
                $groups[] = $open;
                $open = null;
            }
            $open ??= ['start' => $day, 'slice' => false, 'after' => null, 'upTo' => null, 'rows' => 0];
            $open['rows'] += $count;
        }
        if ($open) {
            $groups[] = $open;
        }

        if ($groups === []) {
            return ['rows' => 0, 'chunks' => [['from' => $from, 'to' => $to, 'after' => null, 'upTo' => null, 'rows' => 0]]];
        }

        // Pass 2: stretch whole-day groups over the gaps so the bounds tile the
        // range; a slice stays on its own day.
        $chunks = [];
        foreach ($groups as $i => $group) {
            if ($group['slice']) {
                $chunkFrom = $chunkTo = $group['start'];
            } else {
                $chunkFrom = $i === 0 ? $from : Carbon::parse($chunks[$i - 1]['to'])->addDay()->toDateString();
                $chunkTo = isset($groups[$i + 1])
                    ? Carbon::parse($groups[$i + 1]['start'])->subDay()->toDateString()
                    : $to;
            }

            $chunks[] = [
                'from' => $chunkFrom, 'to' => $chunkTo,
                'after' => $group['after'], 'upTo' => $group['upTo'],
                'rows' => $group['rows'],
            ];
        }

        return ['rows' => $perDay->sum(), 'chunks' => $chunks];
    }

    /**
     * One chunk's rows — never more than the plan allowed for it.
     *
     * @param  array{from: string, to: string, after: ?int, upTo: ?int}  $chunk
     * @return Collection<int, TasksheetEntry>
     */
    public function chunkRows(TasksheetExport $export, array $chunk): Collection
    {
        return $this->rowsQuery($export, $chunk['from'], $chunk['to'])
            ->when($chunk['after'] !== null, fn ($q) => $q->where('user_id', '>', $chunk['after']))
            ->when($chunk['upTo'] !== null, fn ($q) => $q->where('user_id', '<=', $chunk['upTo']))
            ->with(['member', 'team'])
            ->orderBy('date')
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Render one chunk to its partial PDF. Part 1 carries the document header;
     * a later chunk with no rows (a gap that stayed empty) writes nothing,
     * rather than a blank page.
     *
     * @param  array{from: string, to: string, after: ?int, upTo: ?int}  $chunk
     */
    public function renderChunk(TasksheetExport $export, int $index, array $chunk): ?string
    {
        $rows = $this->chunkRows($export, $chunk);
        if ($rows->isEmpty() && $index > 0) {
            return null;
        }

        $requester = $export->requester;

        $pdf = Pdf::loadView('tasksheet.report', [
            'rows' => $rows,
            'team' => $export->team,
            'member' => $export->subject,
            'from' => $export->date_from->toDateString(),
            'to' => $export->date_to->toDateString(),
            'includeFeedback' => $export->include_feedback
                && $requester !== null
                && $this->tasksheet->includesFeedbackFor($requester),
            'generatedBy' => $requester,
            'generatedAt' => $export->created_at,
            'totalRows' => $export->rows_total,
            'showHeader' => $index === 0,
        ])->setPaper('a4', 'landscape');

        $path = $this->partPath($export, $index);
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Merge the parts, in order, into the finished file and drop the parts.
     *
     * FPDI copies each part's already laid-out pages, so memory tracks the
     * size of the finished file rather than dompdf's layout cost. A single
     * part is the finished file already.
     */
    public function merge(TasksheetExport $export): string
    {
        $disk = Storage::disk('local');
        $parts = collect($disk->files($export->directory().'/parts'))->sort()->values();
        $final = $export->directory().'/'.self::FILE;

        if ($parts->isEmpty()) {
            // A retried merge whose first attempt got as far as writing the file.
            if ($disk->exists($final)) {
                return $final;
            }

            throw new RuntimeException('No rendered parts were found to merge.');
        }

        $disk->delete($final);

        if ($parts->count() === 1) {
            $disk->move($parts->first(), $final);
        } else {
            $pdf = new Fpdi;
            $pdf->SetCreator(config('app.name'), true);
            $pdf->SetTitle('Tasksheet report', true);

            foreach ($parts as $part) {
                $pages = $pdf->setSourceFile($disk->path($part));
                for ($page = 1; $page <= $pages; $page++) {
                    $template = $pdf->importPage($page);
                    $size = $pdf->getTemplateSize($template);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($template);
                }
            }

            $pdf->Output('F', $disk->path($final));
        }

        $disk->deleteDirectory($export->directory().'/parts');

        return $final;
    }

    /**
     * Final failure: record why and remove every partial file. Only a running
     * export is touched, so a late failure cannot overwrite a finished one.
     */
    public function fail(int $exportId, string $message): void
    {
        $export = TasksheetExport::find($exportId);
        if (! $export) {
            return;
        }

        $updated = TasksheetExport::whereKey($exportId)
            ->whereIn('status', [TasksheetExport::STATUS_PENDING, TasksheetExport::STATUS_PROCESSING])
            ->update([
                'status' => TasksheetExport::STATUS_FAILED,
                'error' => mb_strimwidth($message, 0, 500, '…'),
                'file_path' => null,
                'completed_at' => now(),
            ]);

        if ($updated) {
            $this->cancelBatch($export);
            Storage::disk('local')->deleteDirectory($export->directory());
        }
    }

    /** The download name — the same one the inline report used. */
    public function filename(TasksheetExport $export): string
    {
        return $this->tasksheet->reportFilename([
            'member' => $export->subject,
            'team' => $export->team,
            'from' => $export->date_from->toDateString(),
            'to' => $export->date_to->toDateString(),
        ]);
    }

    public function partPath(TasksheetExport $export, int $index): string
    {
        return sprintf('%s/parts/%05d.pdf', $export->directory(), $index + 1);
    }

    /** @return Builder<TasksheetEntry> */
    private function rowsQuery(TasksheetExport $export, string $from, string $to): Builder
    {
        return $this->tasksheet->reportQuery($export->team_id, $export->member_id, $from, $to, $export->rowFilters());
    }

    /**
     * An oversized day's members in slices of `$limit`, as (after, upTo]
     * bounds: the first slice is open below and the last open above, so a
     * member who saves a row mid-export still falls in exactly one slice.
     *
     * @return list<array{after: ?int, upTo: ?int}>
     */
    private function memberSlices(TasksheetExport $export, string $day, int $limit): array
    {
        $slices = $this->rowsQuery($export, $day, $day)
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->chunk($limit)
            ->values();

        return $slices->map(fn (Collection $slice, int $k) => [
            'after' => $k === 0 ? null : $slices[$k - 1]->last(),
            'upTo' => $k === $slices->count() - 1 ? null : $slice->last(),
        ])->all();
    }

    private function queue(TasksheetExport $export): TasksheetExport
    {
        $plan = $this->plan($export);
        $export->update(['chunks_total' => count($plan['chunks']), 'rows_total' => $plan['rows']]);

        $exportId = $export->id;
        $jobs = collect($plan['chunks'])
            ->map(fn (array $chunk, int $i) => new RenderTasksheetExportChunk($exportId, $i, Arr::except($chunk, 'rows')))
            ->all();

        // No allowFailures(): a PDF missing a chunk is wrong, so the first
        // permanent failure cancels the rest and the merge never runs.
        $batch = Bus::batch($jobs)
            ->name("Tasksheet export #{$exportId}")
            ->then(static fn () => MergeTasksheetExport::dispatch($exportId))
            ->dispatch();

        // Saved by query: on the sync queue the batch has already finished,
        // and this in-memory model would be stale.
        TasksheetExport::whereKey($exportId)->update(['batch_id' => $batch->id]);

        return $export->refresh();
    }

    private function cancelBatch(TasksheetExport $export): void
    {
        if ($export->batch_id) {
            Bus::findBatch($export->batch_id)?->cancel();
        }
    }
}

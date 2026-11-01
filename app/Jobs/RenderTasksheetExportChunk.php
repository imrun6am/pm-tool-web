<?php

namespace App\Jobs;

use App\Models\TasksheetExport;
use App\Services\TasksheetExportService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One chunk of a tasksheet export: a bounded run of days → one partial PDF.
 * Small, independent units keep each worker's memory flat, give every chunk
 * its own timeout and retries, and let queue:work recycle between them.
 */
class RenderTasksheetExportChunk implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /** Seconds; a chunk is at most `chunk_rows` rows, which dompdf renders in a few. */
    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 30];

    /** @param array{from: string, to: string, after: ?int, upTo: ?int} $chunk */
    public function __construct(public int $exportId, public int $index, public array $chunk) {}

    public function handle(TasksheetExportService $exports): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $export = TasksheetExport::with(['team', 'subject', 'requester'])->find($this->exportId);
        if (! $export || ! $export->isRunning()) {
            return;
        }

        TasksheetExport::whereKey($export->id)
            ->where('status', TasksheetExport::STATUS_PENDING)
            ->update(['status' => TasksheetExport::STATUS_PROCESSING, 'started_at' => now()]);

        $exports->renderChunk($export, $this->index, $this->chunk);

        // Deleted or failed while this part rendered: leave nothing behind.
        if (! TasksheetExport::whereKey($export->id)->where('status', TasksheetExport::STATUS_PROCESSING)->exists()) {
            Storage::disk('local')->deleteDirectory($export->directory());

            return;
        }

        // Display-only counter (the merge is triggered by the batch, not by
        // this): capped so a redelivered job cannot push progress past 100%.
        TasksheetExport::whereKey($export->id)
            ->whereColumn('chunks_done', '<', 'chunks_total')
            ->increment('chunks_done');
    }

    /** Final failure (after retries): the whole export fails and its parts are removed. */
    public function failed(?Throwable $e): void
    {
        app(TasksheetExportService::class)->fail(
            $this->exportId,
            sprintf('Rendering part %d failed: %s', $this->index + 1, $e?->getMessage() ?? 'unknown error'),
        );
    }
}

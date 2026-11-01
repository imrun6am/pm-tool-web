<?php

namespace App\Jobs;

use App\Models\TasksheetExport;
use App\Services\TasksheetExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Runs once every chunk has rendered: merges the parts into the finished PDF. */
class MergeTasksheetExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $exportId) {}

    public function handle(TasksheetExportService $exports): void
    {
        $export = TasksheetExport::find($this->exportId);
        if (! $export || ! $export->isRunning()) {
            return;
        }

        $export->update([
            'status' => TasksheetExport::STATUS_READY,
            'file_path' => $exports->merge($export),
            'error' => null,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        app(TasksheetExportService::class)->fail(
            $this->exportId,
            'Merging the PDF failed: '.($e?->getMessage() ?? 'unknown error'),
        );
    }
}

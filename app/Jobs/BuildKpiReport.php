<?php

namespace App\Jobs;

use App\Models\KpiReport;
use App\Services\Kpi\KpiReportExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Runs after every member chunk has finished: writes the Excel or PDF file. */
class BuildKpiReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $reportId) {}

    public function handle(KpiReportExporter $exporter): void
    {
        $report = KpiReport::find($this->reportId);
        if (! $report || $report->isCompleted()) {
            return;
        }

        if ($report->members_done === 0) {
            $report->update([
                'status' => KpiReport::STATUS_FAILED,
                'error' => $report->members()->whereNotNull('error')->value('error') ?? 'No member could be analysed.',
            ]);

            return;
        }

        $report->update([
            'status' => KpiReport::STATUS_COMPLETED,
            'file_path' => $exporter->write($report),
            'error' => null,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        KpiReport::whereKey($this->reportId)->update([
            'status' => KpiReport::STATUS_FAILED,
            'error' => mb_strimwidth('Building the file failed: '.($e?->getMessage() ?? 'unknown error'), 0, 500, '…'),
        ]);
    }
}

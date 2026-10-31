<?php

namespace App\Jobs;

use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use App\Models\KpiReport;
use App\Models\KpiReportMember;
use App\Services\Kpi\KpiAnalyzer;
use App\Services\Kpi\KpiReviewService;
use App\Services\Kpi\MissingApiKeyException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One chunk of a KPI report: a single member's tasksheet → Claude → one
 * kpi_report_members row. Small, independent units keep each worker's memory
 * flat and let a failed member retry without redoing the rest.
 */
class AnalyseKpiMember implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /** Seconds; a long member history at high effort can take several minutes. */
    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $reportMemberId) {}

    public function handle(KpiReviewService $kpi, KpiAnalyzer $analyzer): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $row = KpiReportMember::with(['report', 'member'])->find($this->reportMemberId);
        if (! $row || $row->status !== KpiReportMember::STATUS_PENDING) {
            return;
        }

        $report = $row->report;
        KpiReport::whereKey($report->id)
            ->where('status', KpiReport::STATUS_QUEUED)
            ->update(['status' => KpiReport::STATUS_PROCESSING]);

        $data = $kpi->memberInput($row->member, $report->period_start->toDateString(), $report->period_end->toDateString(), $report->teamFilter());

        $result = ['days' => [], 'months' => [], 'overall' => null, 'usage' => ['input_tokens' => 0, 'output_tokens' => 0]];
        if ($data['input']['entries'] !== []) {
            try {
                $result = $analyzer->analyse($data['input']);
            } catch (MissingApiKeyException|AuthenticationException|PermissionDeniedException|BadRequestException $e) {
                $this->fail($e); // retrying a configuration or request error cannot succeed

                return;
            }
        }

        $row->update([
            'status' => KpiReportMember::STATUS_COMPLETED,
            'analysis' => [
                'facts' => $data['facts'],
                'days' => $result['days'],
                'months' => $result['months'],
                'overall' => $result['overall'],
            ],
            'error' => null,
            'input_tokens' => $result['usage']['input_tokens'],
            'output_tokens' => $result['usage']['output_tokens'],
        ]);

        KpiReport::whereKey($report->id)->increment('members_done');
    }

    /** Final failure (after retries): the member still appears in the report, flagged. */
    public function failed(?Throwable $e): void
    {
        $row = KpiReportMember::find($this->reportMemberId);
        if (! $row || $row->status !== KpiReportMember::STATUS_PENDING) {
            return;
        }

        $row->update(['status' => KpiReportMember::STATUS_FAILED, 'error' => mb_strimwidth($e?->getMessage() ?? 'Unknown error', 0, 500, '…')]);
        KpiReport::whereKey($row->kpi_report_id)->increment('members_failed');
    }
}

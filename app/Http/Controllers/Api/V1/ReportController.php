<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ReadsTimeReportFilters;
use App\Models\Release;
use App\Services\FlowReportService;
use App\Services\ReleaseProgressService;
use App\Services\TimeReportService;
use App\Support\TimeReportCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Team-level flow, hours against estimates, and a release's progress. */
class ReportController extends ApiController
{
    use ReadsTimeReportFilters;

    public function flow(Request $request, FlowReportService $flow): JsonResponse
    {
        $report = $flow->report($this->filterId($request, 'team_id'));

        return $this->ok([
            'weeks' => array_map(fn (array $w) => ['start' => $w['start']->toDateString(), 'end' => $w['end']->toDateString(), 'current' => $w['current']], $report['weeks']),
            'flow' => $report['flow'],
            'arrivals' => $report['arrivals'],
            'completions' => $report['completions'],
            'cycle' => array_map(fn (array $c) => ['completed_at' => $c['completed_at']->toIso8601String()] + $c, $report['cycle']),
            'cycle_p50' => $report['cycle_p50'],
            'cycle_p85' => $report['cycle_p85'],
            'finished' => $report['finished'],
            'in_progress' => $report['in_progress'],
            'aging' => $report['aging'],
        ]);
    }

    public function time(Request $request, TimeReportService $time): JsonResponse
    {
        $filters = $this->timeReportFilters($request);

        return $this->ok($time->report($filters, $request->user()) + [
            'from' => $filters['from']->toDateString(),
            'to' => $filters['to']->toDateString(),
        ]);
    }

    public function timeExport(Request $request, TimeReportService $time): StreamedResponse
    {
        $filters = $this->timeReportFilters($request);

        return TimeReportCsv::download(
            $time->entries($filters, $request->user()),
            sprintf('time_%s_%s.csv', $filters['from']->toDateString(), $filters['to']->toDateString()),
        );
    }

    public function progress(Release $release, ReleaseProgressService $progress): JsonResponse
    {
        $forecast = $progress->forecast($release);

        return $this->ok([
            'burn_up' => array_map(fn (array $p) => ['date' => $p['date']->toDateString()] + $p, $progress->burnUp($release)),
            'forecast' => [
                'dates' => $forecast['dates'] ? array_map(fn ($d) => $d->toDateString(), $forecast['dates']) : null,
            ] + $forecast,
        ]);
    }
}

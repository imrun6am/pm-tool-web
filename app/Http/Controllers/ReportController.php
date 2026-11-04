<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsTimeReportFilters;
use App\Models\Release;
use App\Models\Team;
use App\Services\FlowReportService;
use App\Services\TimeReportService;
use App\Support\TimeReportCsv;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ReadsTimeReportFilters;

    public function flow(Request $request, FlowReportService $flow): View
    {
        $teamId = $request->filled('team_id') ? $request->integer('team_id') : null;

        return view('reports.flow', [
            'report' => $flow->report($teamId),
            'teams' => Team::orderBy('name')->get(),
            'teamId' => $teamId,
        ]);
    }

    public function time(Request $request, TimeReportService $time): View
    {
        $filters = $this->timeReportFilters($request);
        $viewer = $request->user();

        return view('reports.time', [
            'report' => $time->report($filters, $viewer),
            'filters' => $filters,
            'teams' => Team::query()->unless($viewer->canOverseeAllTeams(), fn ($q) => $q->where('team_lead_id', $viewer->id))->orderBy('name')->get(),
            'releases' => Release::query()->unless($viewer->canOverseeAllTeams(), fn ($q) => $q->whereIn('team_id', Team::where('team_lead_id', $viewer->id)->select('id')))->orderByDesc('year')->orderBy('name')->get(),
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
}

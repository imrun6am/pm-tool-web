<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsPeopleReportFilters;
use App\Models\Team;
use App\Models\User;
use App\Services\PeopleAnalyticsService;
use App\Support\PeopleReportCsv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin analytics per team and per person; the routes are behind `admin`. */
class PeopleReportController extends Controller
{
    use ReadsPeopleReportFilters;

    public function index(Request $request, PeopleAnalyticsService $people): View
    {
        $filters = $this->peopleReportFilters($request);

        return view('reports.people.index', [
            'report' => $people->report($filters),
            'filters' => $filters,
            'teams' => Team::orderBy('name')->get(['id', 'name', 'archived_at']),
            'presets' => PeopleAnalyticsService::presets(),
        ]);
    }

    public function show(Request $request, User $user, PeopleAnalyticsService $people): View
    {
        $filters = $this->peopleReportFilters($request);

        return view('reports.people.show', [
            'report' => $people->person($user, $filters),
            'filters' => $filters,
            'teams' => Team::orderBy('name')->get(['id', 'name', 'archived_at']),
            'presets' => PeopleAnalyticsService::presets(),
        ]);
    }

    public function exportCsv(Request $request, PeopleAnalyticsService $people): StreamedResponse
    {
        $filters = $this->peopleReportFilters($request);
        $report = $people->report($filters);
        $name = fn (string $what) => sprintf('%s_%s_%s.csv', $what, $filters['from']->toDateString(), $filters['to']->toDateString());

        return $request->input('level') === 'teams'
            ? PeopleReportCsv::teams($report['teams'], $name('team_analytics'))
            : PeopleReportCsv::people($report['people'], $name('people_analytics'));
    }

    public function exportPdf(Request $request, PeopleAnalyticsService $people): Response
    {
        $filters = $this->peopleReportFilters($request);

        return Pdf::loadView('reports.people.pdf', ['report' => $people->report($filters)])
            ->setPaper('a4', 'landscape')
            ->download(sprintf('people_analytics_%s_%s.pdf', $filters['from']->toDateString(), $filters['to']->toDateString()));
    }

    public function personTasks(Request $request, User $user, PeopleAnalyticsService $people): StreamedResponse
    {
        $filters = $this->peopleReportFilters($request);
        $report = $people->person($user, $filters);

        return PeopleReportCsv::tasks($report['finished'], $report['open'], sprintf(
            'tasks_%s_%s_%s.csv', Str::slug($user->name), $filters['from']->toDateString(), $filters['to']->toDateString(),
        ));
    }

    public function personPdf(Request $request, User $user, PeopleAnalyticsService $people): Response
    {
        $filters = $this->peopleReportFilters($request);

        return Pdf::loadView('reports.people.person-pdf', ['report' => $people->person($user, $filters)])
            ->setPaper('a4', 'landscape')
            ->download(sprintf('analytics_%s_%s_%s.pdf', Str::slug($user->name), $filters['from']->toDateString(), $filters['to']->toDateString()));
    }
}

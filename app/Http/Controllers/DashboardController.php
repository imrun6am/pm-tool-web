<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Services\DashboardFilters;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): View
    {
        // Developers/QA get a personal "what am I doing today" dashboard
        // instead of the release-planning timeline.
        if ($request->user()->hasLimitedAccess()) {
            return view('dashboard-member', $this->dashboard->memberSnapshot($request->user()));
        }

        $filters = DashboardFilters::fromRequest($request);

        $releases = $this->dashboard->timelineReleases($filters);
        $conflicts = $this->dashboard->conflictFlags($releases);

        return view('dashboard', [
            'groups' => $this->dashboard->groups($releases, $filters, $conflicts),
            'months' => $this->dashboard->months($filters),
            'rangeStart' => $filters->rangeStart,
            'rangeEnd' => $filters->rangeEnd,
            'analytics' => $this->dashboard->analytics($filters, $releases, $conflicts),
            'phaseColors' => Release::PHASE_COLORS,
            'phaseLabels' => Release::PHASES,
            'hasConflicts' => in_array(true, $conflicts, true),
            'filters' => [
                'year' => $filters->year,
                'quarter' => $filters->quarter,
                'project_id' => $filters->projectId,
                'team_id' => $filters->teamId,
                'group_by' => $filters->groupBy,
            ],
            'years' => $this->dashboard->availableYears($filters->year),
            ...$this->dashboard->filterOptions(),
        ]);
    }
}

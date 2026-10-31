<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\EventResource;
use App\Http\Resources\V1\ReleaseSummaryResource;
use App\Http\Resources\V1\TasksheetEntryResource;
use App\Http\Resources\V1\TaskSummaryResource;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Models\Release;
use App\Models\Team;
use App\Services\DashboardFilters;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The dashboard in its two forms: the planning timeline for full-access roles,
 * and the personal member view for developers and QA.
 *
 * All computation lives in DashboardService, shared with the Blade dashboard.
 * What remains here is presentation: the service returns models and Carbon
 * instances under the keys the Blade views consume, and the private presenters
 * below rename them into this API's published snake_case contract. That naming
 * difference is the one thing that legitimately differs per delivery format.
 */
class DashboardController extends ApiController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $request->user()->hasLimitedAccess()
            ? $this->memberDashboard($request)
            : $this->planningDashboard($request);
    }

    private function planningDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $filters = DashboardFilters::fromRequest($request);

        $releases = $this->dashboard->timelineReleases($filters);
        $conflicts = $this->dashboard->conflictFlags($releases);
        $options = $this->dashboard->filterOptions();

        return $this->ok([
            'mode' => 'planning',
            'range' => [
                'start' => $filters->rangeStart->toDateString(),
                'end' => $filters->rangeEnd->toDateString(),
            ],
            'months' => $this->dashboard->months($filters),
            'groups' => $this->presentGroups(
                $request,
                $this->dashboard->groups($releases, $filters, $conflicts)
            ),
            'has_conflicts' => in_array(true, $conflicts, true),
            'conflicting_release_ids' => array_map('intval', array_keys(array_filter($conflicts))),
            'analytics' => $this->presentAnalytics(
                $this->dashboard->analytics($filters, $releases, $conflicts)
            ),
            'phase_colors' => Release::PHASE_COLORS,
            'phase_labels' => Release::PHASES,
            'filters' => [
                'year' => $filters->year,
                'quarter' => $filters->quarter,
                'project_id' => $filters->projectId,
                'team_id' => $filters->teamId,
                'group_by' => $filters->groupBy,
            ],
            'years' => $this->dashboard->availableYears($filters->year),
            'projects' => $options['projects']->map->only(['id', 'name', 'color'])->values(),
            'teams' => TeamSummaryResource::collection($options['teams'])->resolve($request),
        ]);
    }

    private function memberDashboard(Request $request): JsonResponse
    {
        $snapshot = $this->dashboard->memberSnapshot($request->user());

        return $this->ok([
            'mode' => 'member',
            'date' => today()->toDateString(),
            'my_tasks' => TaskSummaryResource::collection($snapshot['tasks'])->resolve($request),
            'overdue_count' => $snapshot['overdueCount'],
            'tasksheet_today' => $snapshot['teams']->map(fn (Team $team) => [
                'team' => (new TeamSummaryResource($team))->resolve($request),
                'entry' => ($entry = $snapshot['sheetEntries']->get($team->id))
                    ? (new TasksheetEntryResource($entry))->resolve($request)
                    : null,
            ])->values()->all(),
            'upcoming_meetings' => EventResource::collection($snapshot['meetings'])->resolve($request),
        ]);
    }

    /**
     * Map the shared timeline geometry into JSON: the release model becomes a
     * resource, and the Carbon phase bounds become date strings.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function presentGroups(Request $request, array $groups): array
    {
        return array_map(fn (array $group) => [
            ...$group,
            'bars' => array_map(fn (array $bar) => [
                ...$bar,
                'release' => (new ReleaseSummaryResource($bar['release']))->resolve($request),
                'phases' => array_map(fn (array $phase) => [
                    'phase' => $phase['phase'],
                    'label' => $phase['label'],
                    'offset' => $phase['offset'],
                    'width' => $phase['width'],
                    'color' => $phase['color'],
                    'start_date' => $phase['start']?->toDateString(),
                    'end_date' => $phase['end']?->toDateString(),
                ], $bar['phases']),
            ], $group['bars']),
        ], $groups);
    }

    /**
     * Rename the analytics roll-up into this API's snake_case contract.
     *
     * `statusLabels` is dropped: the API publishes every enumeration once at
     * `GET /meta`, so repeating the labels on each dashboard response would be
     * a second source of truth for the same list.
     *
     * @param  array<string, mixed>  $analytics
     * @return array<string, mixed>
     */
    private function presentAnalytics(array $analytics): array
    {
        return [
            'year' => $analytics['year'],
            'period_label' => $analytics['periodLabel'],
            'active' => $analytics['active'],
            'completed_in_period' => $analytics['completedThisYear'],
            'upcoming' => $analytics['upcoming'],
            'conflict_count' => $analytics['conflictCount'],
            'teams_double_booked' => $analytics['teamsDoubleBooked'],
            'monthly' => array_map(fn (array $m) => [
                'label' => $m['label'],
                'count' => $m['count'],
                'is_current' => $m['current'],
            ], $analytics['monthly']),
            'monthly_max' => $analytics['monthlyMax'],
            'status_counts' => $analytics['statusCounts'],
            'task_total' => $analytics['taskTotal'],
            'done_pct' => $analytics['donePct'],
            'team_workload' => $analytics['teamWorkload'],
            'team_workload_max' => $analytics['teamWorkloadMax'],
        ];
    }
}

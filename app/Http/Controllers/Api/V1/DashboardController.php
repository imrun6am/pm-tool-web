<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\EventResource;
use App\Http\Resources\V1\ReleaseSummaryResource;
use App\Http\Resources\V1\TasksheetEntryResource;
use App\Http\Resources\V1\TaskSummaryResource;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\Release;
use App\Models\Team;
use App\Services\DashboardFilters;
use App\Services\DashboardService;
use App\Services\DeliveryAnalyticsService;
use App\Services\MemberAnalyticsService;
use App\Services\MemberPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The dashboard in its two forms: the planning timeline for full-access roles,
 * and the personal member view for developers and QA.
 *
 * All computation lives in DashboardService and MemberAnalyticsService, shared
 * with the Blade dashboard.
 * What remains here is presentation: the service returns models and Carbon
 * instances under the keys the Blade views consume, and the private presenters
 * below rename them into this API's published snake_case contract. That naming
 * difference is the one thing that legitimately differs per delivery format.
 */
class DashboardController extends ApiController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly MemberAnalyticsService $memberAnalytics,
        private readonly DeliveryAnalyticsService $delivery,
    ) {}

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
            'delivery' => $this->presentDelivery(
                $request,
                $this->delivery->forUser($request->user(), $filters, $releases, $conflicts)
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

    /**
     * Rename the role-tailored delivery health into snake_case. Sections the
     * audience may not see (people, attention lists, compliance for viewers)
     * stay present as null, so a client can switch on presence.
     *
     * @param  array<string, mixed>  $delivery
     * @return array<string, mixed>
     */
    private function presentDelivery(Request $request, array $delivery): array
    {
        $snake = fn (array $values) => collect($values)->mapWithKeys(fn ($v, $k) => [Str::snake($k) => $v])->all();
        $tasks = fn ($tasks) => TaskSummaryResource::collection($tasks)->resolve($request);
        $window = $delivery['window'];
        $compliance = $delivery['compliance'];

        return [
            'audience' => $delivery['audience'],
            'scope_label' => $delivery['scopeLabel'],
            'team_ids' => $delivery['scopedTeamIds'],
            'window' => [
                'days' => $window['days'],
                'start' => $window['start']->toDateString(),
                'end' => $window['end']->toDateString(),
                'previous_start' => $window['previousStart']->toDateString(),
                'previous_end' => $window['previousEnd']->toDateString(),
            ],
            'kpis' => $snake($delivery['kpis']),
            'backlog' => $snake($delivery['backlog']),
            'aging' => $delivery['aging'],
            'weekly' => array_map(fn (array $w) => [
                'week_start' => $w['weekStart']->toDateString(),
                'count' => $w['count'],
                'is_current' => $w['current'],
                'is_best' => $w['best'],
            ], $delivery['weekly']),
            'team_load' => $delivery['teamLoad'],
            'people' => $delivery['people'] === null ? null : array_map(fn (array $p) => [
                'user' => (new UserSummaryResource($p['user']))->resolve($request),
                'open' => $p['open'],
                'in_progress' => $p['inProgress'],
                'in_review' => $p['inReview'],
                'overdue' => $p['overdue'],
            ], $delivery['people']),
            'attention' => $delivery['attention'] === null ? null : [
                'overdue' => $tasks($delivery['attention']['overdue']),
                'in_review' => $tasks($delivery['attention']['inReview']),
            ],
            'releases' => array_map(fn (array $r) => [
                'release' => (new ReleaseSummaryResource($r['release']))->resolve($request),
                'total' => $r['total'],
                'done' => $r['done'],
                'pct' => $r['pct'],
                'elapsed_pct' => $r['elapsedPct'],
                'late_phases' => $r['latePhases'],
                'flags' => $r['flags'],
                'at_risk' => $r['atRisk'],
            ], $delivery['releases']),
            'at_risk_count' => $delivery['atRiskCount'],
            'compliance' => $compliance === null ? null : [
                ...$snake(collect($compliance)->except('teams')->all()),
                'teams' => array_map(fn (array $t) => [
                    'team' => (new TeamSummaryResource($t['team']))->resolve($request),
                    ...$snake(collect($t)->except('team')->all()),
                ], $compliance['teams']),
            ],
            'mine' => $snake($delivery['mine']),
        ];
    }

    private function memberDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', Rule::in(array_keys(MemberPeriod::PERIODS))],
        ]);

        $snapshot = $this->dashboard->memberSnapshot($request->user());
        $analytics = $this->memberAnalytics->forUser($request->user(), MemberPeriod::fromRequest($request));

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
            'analytics' => $this->presentMemberAnalytics($request, $analytics),
        ]);
    }

    /**
     * Rename the member's personal analytics into the snake_case contract:
     * Carbon weeks and days become dates, completion moments ISO-8601, and
     * tasks summary resources. The heatmap is flattened to past days only —
     * the week-column layout is a drawing concern, not data.
     *
     * @param  array<string, mixed>  $analytics
     * @return array<string, mixed>
     */
    private function presentMemberAnalytics(Request $request, array $analytics): array
    {
        $period = $analytics['period'];
        $task = fn ($task) => (new TaskSummaryResource($task))->resolve($request);
        $snake = fn (array $values) => collect($values)->mapWithKeys(fn ($v, $k) => [Str::snake($k) => $v])->all();

        return [
            'period' => [
                'key' => $period->key,
                'label' => $period->label(),
                'start' => $period->start->toDateString(),
                'end' => $period->end->toDateString(),
                'previous_start' => $period->previousStart->toDateString(),
                'previous_end' => $period->previousEnd->toDateString(),
            ],
            'today' => $snake($analytics['today']),
            'focus' => array_map(fn (array $f) => [
                'reason' => $f['reason'],
                'task' => $task($f['task']),
            ], $analytics['focus']),
            'kpis' => $snake($analytics['kpis']),
            'weekly' => array_map(fn (array $w) => [
                'week_start' => $w['weekStart']->toDateString(),
                'count' => $w['count'],
                'is_current' => $w['current'],
                'is_best' => $w['best'],
            ], $analytics['weekly']),
            'best_week' => [
                'week_start' => $analytics['bestWeek']['weekStart']?->toDateString(),
                'count' => $analytics['bestWeek']['count'],
            ],
            'pace' => $snake($analytics['pace']),
            'status_counts' => $analytics['statusCounts'],
            'task_total' => $analytics['taskTotal'],
            'heatmap' => [
                'total' => $analytics['heatmap']['total'],
                'active_days' => $analytics['heatmap']['activeDays'],
                'days' => collect($analytics['heatmap']['weeks'])->flatten(1)
                    ->reject(fn (array $d) => $d['future'])
                    ->map(fn (array $d) => [
                        'date' => $d['date']->toDateString(),
                        'count' => $d['count'],
                        'level' => $d['level'],
                        'tasks' => $d['tasks'],
                        'comments' => $d['comments'],
                        'tasksheet_logged' => $d['logged'],
                    ])->values()->all(),
            ],
            'streak' => $snake($analytics['streak']),
            'lead_time_trend' => array_map(fn (array $w) => [
                'week_start' => $w['weekStart']->toDateString(),
                'days' => $w['days'],
            ], $analytics['leadTimeTrend']),
            'recent' => array_map(fn (array $r) => [
                'completed_at' => $r['completedAt']->toIso8601String(),
                'task' => $task($r['task']),
            ], $analytics['recent']),
            'on_team' => $analytics['onTeam'],
            'has_history' => $analytics['hasHistory'],
        ];
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

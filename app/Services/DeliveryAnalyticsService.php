<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\Metric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivery health for the planning dashboard, tailored to who is looking:
 *
 *  - `org`    (admin, CTO, tech lead): every team, or the team filter.
 *  - `team`   (team lead): the teams they lead, unless they filter to another.
 *  - `viewer`: the same portfolio numbers with nothing people-level — no
 *              per-person load, no compliance, no named task lists.
 *
 * The scope follows the dashboard's own project/team filters, so these numbers
 * and the timeline above them describe the same slice. Archived tasks are
 * never counted (Task::UNCOUNTED_STATUSES); "open" means not Done/Archive.
 *
 * Throughput and cycle time are read from the RecordsActivity audit trail,
 * as on the member dashboard: completed = the last move into Done, started =
 * the first move to In Progress, reworked = ever sent to Recheck. Done tasks
 * with no logged transition fall back to `updated_at` for counts only.
 *
 * Every number comes from an aggregate query or a bounded list (completions
 * in the trend window, capped task lists), about a dozen queries in all,
 * independent of history size. Performance scores and lead feedback never
 * appear here.
 */
class DeliveryAnalyticsService
{
    public const TREND_WEEKS = 12;

    /** The rolling KPI window, compared with the one before it. */
    public const WINDOW_DAYS = 28;

    /** Working days of tasksheet/standup history behind the compliance rates. */
    public const COMPLIANCE_DAYS = 10;

    public const DUE_SOON_DAYS = 7;

    public const LIST_LIMIT = 6;

    public const PEOPLE_LIMIT = 8;

    /** Open-task age buckets, in days since creation: label => [min, max|null]. */
    public const AGING = [
        '0–7 days' => [0, 7],
        '8–14 days' => [8, 14],
        '15–30 days' => [15, 30],
        'Over 30 days' => [31, null],
    ];

    /** A release is "behind" when its done share trails elapsed time by this many points. */
    public const BEHIND_POINTS = 25;

    /** Which tailored view a planning-dashboard user gets. */
    public function audience(User $user): string
    {
        return match (true) {
            $user->isViewer() || ! $user->isLead() => 'viewer',
            $user->isTeamLead() => 'team',
            default => 'org',
        };
    }

    /**
     * @param  Collection<int, Release>  $timelineReleases
     * @param  array<int, bool>  $conflicts
     * @return array<string, mixed>
     */
    public function forUser(User $user, DashboardFilters $filters, Collection $timelineReleases, array $conflicts): array
    {
        $audience = $this->audience($user);
        $people = $audience !== 'viewer';
        $today = Carbon::today();

        [$teamIds, $scopeLabel] = $this->scope($user, $audience, $filters);

        $tasks = fn (): Builder => Task::query()
            ->when($teamIds !== null, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->when($filters->projectId, fn ($q) => $q->whereIn(
                'release_id',
                Release::select('id')->where('project_id', $filters->projectId)
            ));
        $open = fn (): Builder => $tasks()->whereNotIn('status', Task::DONE_STATUSES);

        $window = [
            'days' => self::WINDOW_DAYS,
            'start' => $today->copy()->subDays(self::WINDOW_DAYS - 1),
            'end' => $today->copy()->endOfDay(),
            'previousStart' => $today->copy()->subDays(2 * self::WINDOW_DAYS - 1),
            'previousEnd' => $today->copy()->subDays(self::WINDOW_DAYS)->endOfDay(),
        ];

        $thisWeek = $today->copy()->startOfWeek();
        $trendStart = $thisWeek->copy()->subWeeks(self::TREND_WEEKS - 1);
        $completions = $this->completions($tasks, $trendStart->copy()->min($window['previousStart']));

        $releases = $teamIds === null
            ? $timelineReleases
            : $timelineReleases->whereIn('team_id', $teamIds)->values();
        $releaseHealth = $this->releaseHealth($releases, $conflicts, $today);

        return [
            'audience' => $audience,
            'scopeLabel' => $scopeLabel,
            'scopedTeamIds' => $teamIds,
            'window' => $window,
            'kpis' => $this->kpis($completions, $window),
            'backlog' => $this->backlog($open, $today),
            'aging' => $this->aging($open, $today),
            'weekly' => $this->weekly($completions, $thisWeek),
            'teamLoad' => $this->teamLoad($open, $today),
            'people' => $people ? $this->peopleLoad($open, $today) : null,
            'attention' => $people ? [
                'overdue' => $open()->with(['assignee', 'release'])
                    ->whereDate('due_date', '<', $today->toDateString())
                    ->orderBy('due_date')->orderBy('id')
                    ->limit(self::LIST_LIMIT)->get(),
                // Oldest-touched first: what has waited longest for a reviewer.
                'inReview' => $open()->with(['assignee', 'release'])
                    ->where('status', 'in_review')
                    ->orderBy('updated_at')->orderBy('id')
                    ->limit(self::LIST_LIMIT)->get(),
            ] : null,
            'releases' => $releaseHealth,
            'atRiskCount' => count(array_filter($releaseHealth, fn (array $r) => $r['atRisk'])),
            'compliance' => $people ? $this->compliance($teamIds, $today) : null,
            'mine' => $this->mine($user, $today),
        ];
    }

    /**
     * The team ids in view (null = every team) and a label for them.
     *
     * @return array{0: ?list<int>, 1: string}
     */
    private function scope(User $user, string $audience, DashboardFilters $filters): array
    {
        if ($filters->teamId) {
            return [[$filters->teamId], (string) Team::whereKey($filters->teamId)->value('name')];
        }

        if ($audience === 'team') {
            $led = $user->ledTeams()->get(['id', 'name']);
            if ($led->isNotEmpty()) {
                return [$led->pluck('id')->all(), $led->pluck('name')->implode(', ')];
            }
        }

        return [null, 'All teams'];
    }

    /**
     * Done tasks in scope completed since `$from`, with their moments.
     *
     * @param  \Closure(): Builder  $tasks
     * @return Collection<int, array{completedAt: Carbon, startedAt: ?Carbon, reworked: bool, estimated: bool, dueDate: ?Carbon}>
     */
    private function completions(\Closure $tasks, Carbon $from): Collection
    {
        // A task completed since $from was necessarily saved since then, so
        // updated_at bounds the candidate set without reading old history.
        $done = fn (): Builder => $tasks()->where('status', 'done')->where('updated_at', '>=', $from);

        $candidates = $done()->get(['id', 'due_date', 'created_at', 'updated_at']);

        $history = Activity::query()
            ->where('subject_type', (new Task)->getMorphClass())
            ->whereIn('subject_id', $done()->select('id'))
            ->whereIn('event', ['created', 'updated'])
            ->whereNotNull('properties->attributes->status')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'subject_id', 'event', 'properties', 'created_at'])
            ->groupBy('subject_id');

        return $candidates->map(function (Task $task) use ($history) {
            $startedAt = $completedAt = null;
            $reworked = false;

            foreach ($history->get($task->id, []) as $activity) {
                $new = $activity->properties['attributes']['status'] ?? null;
                $old = $activity->event === 'created' ? null : ($activity->properties['old']['status'] ?? null);

                if ($new === 'in_progress' && $startedAt === null) {
                    $startedAt = $activity->created_at;
                }
                if ($new === 'recheck') {
                    $reworked = true;
                }
                if ($new === 'done' && ! in_array($old, Task::DONE_STATUSES, true)) {
                    $completedAt = $activity->created_at;
                }
            }

            return [
                'completedAt' => $completedAt ?? $task->updated_at,
                'startedAt' => $startedAt && $completedAt && $startedAt->lte($completedAt) ? $startedAt : null,
                'reworked' => $reworked,
                'estimated' => $completedAt === null,
                'dueDate' => $task->due_date,
            ];
        })->filter(fn (array $c) => $c['completedAt']->gte($from))->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $completions
     * @param  array<string, mixed>  $window
     * @return array<string, array{current: int|float|null, previous: int|float|null, change: int|float|null}>
     */
    private function kpis(Collection $completions, array $window): array
    {
        $stats = function (Carbon $from, Carbon $to) use ($completions) {
            $done = $completions->filter(fn (array $c) => $c['completedAt']->betweenIncluded($from, $to));
            $dated = $done->filter(fn (array $c) => $c['dueDate'] !== null);
            $cycles = $done->filter(fn (array $c) => $c['startedAt'] !== null)
                ->map(fn (array $c) => $c['startedAt']->diffInSeconds($c['completedAt']) / 86400);

            return [
                'throughput' => $done->count(),
                'cycleDays' => $cycles->isEmpty() ? null : round($cycles->avg(), 1),
                'onTimeRate' => Metric::pct(
                    $dated->filter(fn (array $c) => $c['completedAt']->toDateString() <= $c['dueDate']->toDateString())->count(),
                    $dated->count()
                ),
                // Of the work finished, how much had been sent back to Recheck.
                'reworkRate' => Metric::pct($done->where('reworked', true)->count(), $done->count()),
            ];
        };

        $current = $stats($window['start'], $window['end']);
        $previous = $stats($window['previousStart'], $window['previousEnd']);

        return collect($current)->map(fn ($value, $key) => Metric::compare($value, $previous[$key]))->all();
    }

    /**
     * @param  \Closure(): Builder  $open
     * @return array<string, int>
     */
    private function backlog(\Closure $open, Carbon $today): array
    {
        $row = $open()->selectRaw(
            'count(*) as open_count,
             sum(case when due_date < ? then 1 else 0 end) as overdue,
             sum(case when due_date >= ? and due_date <= ? then 1 else 0 end) as due_soon,
             sum(case when status = ? then 1 else 0 end) as in_review,
             sum(case when status = ? then 1 else 0 end) as recheck,
             sum(case when assignee_id is null then 1 else 0 end) as unassigned',
            [
                $today->toDateString(),
                $today->toDateString(), $today->copy()->addDays(self::DUE_SOON_DAYS)->toDateString(),
                'in_review', 'recheck',
            ]
        )->first();

        return [
            'open' => (int) $row->open_count,
            'overdue' => (int) $row->overdue,
            'dueSoon' => (int) $row->due_soon,
            'inReview' => (int) $row->in_review,
            'recheck' => (int) $row->recheck,
            'unassigned' => (int) $row->unassigned,
        ];
    }

    /**
     * Open work by how long ago it was created.
     *
     * @param  \Closure(): Builder  $open
     * @return list<array{label: string, count: int}>
     */
    private function aging(\Closure $open, Carbon $today): array
    {
        $cases = [];
        $bindings = [];
        foreach (array_values(self::AGING) as $i => [$min, $max]) {
            // Age in days ≥ min ⇔ created before (today − min + 1 day).
            $upper = $today->copy()->subDays($min - 1)->toDateTimeString();
            if ($max === null) {
                $cases[] = "sum(case when created_at < ? then 1 else 0 end) as b{$i}";
                $bindings[] = $upper;
            } else {
                $cases[] = "sum(case when created_at < ? and created_at >= ? then 1 else 0 end) as b{$i}";
                array_push($bindings, $upper, $today->copy()->subDays($max)->toDateTimeString());
            }
        }

        $row = $open()->selectRaw(implode(', ', $cases), $bindings)->first();

        return collect(array_keys(self::AGING))
            ->map(fn (string $label, int $i) => ['label' => $label, 'count' => (int) $row->{"b{$i}"}])
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $completions
     * @return list<array{weekStart: Carbon, count: int, current: bool, best: bool}>
     */
    private function weekly(Collection $completions, Carbon $thisWeek): array
    {
        $weeks = [];
        for ($i = self::TREND_WEEKS - 1; $i >= 0; $i--) {
            $start = $thisWeek->copy()->subWeeks($i);
            $end = $start->copy()->endOfWeek();
            $weeks[] = [
                'weekStart' => $start,
                'count' => $completions->filter(fn (array $c) => $c['completedAt']->betweenIncluded($start, $end))->count(),
                'current' => $i === 0,
                'best' => false,
            ];
        }

        // On a tie the most recent week wins, matching the all-time best week.
        $max = max(array_column($weeks, 'count'));
        if ($max > 0) {
            $weeks[max(array_keys(array_column($weeks, 'count'), $max, true))]['best'] = true;
        }

        return $weeks;
    }

    /**
     * Open and overdue work per team, busiest first.
     *
     * @param  \Closure(): Builder  $open
     * @return list<array{id: ?int, name: string, color: string, open: int, overdue: int}>
     */
    private function teamLoad(\Closure $open, Carbon $today): array
    {
        $rows = $open()
            ->selectRaw('team_id, count(*) as open_count, sum(case when due_date < ? then 1 else 0 end) as overdue', [$today->toDateString()])
            ->groupBy('team_id')
            ->get();

        $teams = Team::whereIn('id', $rows->pluck('team_id')->filter())->get(['id', 'name', 'color'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'id' => $row->team_id,
            'name' => $teams[$row->team_id]->name ?? 'No team',
            'color' => $teams[$row->team_id]->color ?? '#94a3b8',
            'open' => (int) $row->open_count,
            'overdue' => (int) $row->overdue,
        ])->sortByDesc('open')->values()->all();
    }

    /**
     * The busiest assignees in scope.
     *
     * @param  \Closure(): Builder  $open
     * @return list<array{user: User, open: int, inProgress: int, inReview: int, overdue: int}>
     */
    private function peopleLoad(\Closure $open, Carbon $today): array
    {
        $rows = $open()
            ->whereNotNull('assignee_id')
            ->selectRaw(
                'assignee_id, count(*) as open_count,
                 sum(case when status = ? then 1 else 0 end) as in_progress,
                 sum(case when status = ? then 1 else 0 end) as in_review,
                 sum(case when due_date < ? then 1 else 0 end) as overdue',
                ['in_progress', 'in_review', $today->toDateString()]
            )
            ->groupBy('assignee_id')
            ->orderByDesc('open_count')
            ->orderBy('assignee_id')
            ->limit(self::PEOPLE_LIMIT)
            ->get();

        $users = User::withTrashed()->whereIn('id', $rows->pluck('assignee_id'))->get()->keyBy('id');

        return $rows->filter(fn ($row) => $users->has($row->assignee_id))->map(fn ($row) => [
            'user' => $users[$row->assignee_id],
            'open' => (int) $row->open_count,
            'inProgress' => (int) $row->in_progress,
            'inReview' => (int) $row->in_review,
            'overdue' => (int) $row->overdue,
        ])->values()->all();
    }

    /**
     * Each ongoing release's progress against its calendar, with the reasons
     * it may be at risk. At-risk releases first, then by end date.
     *
     * @param  Collection<int, Release>  $releases
     * @param  array<int, bool>  $conflicts
     * @return list<array<string, mixed>>
     */
    private function releaseHealth(Collection $releases, array $conflicts, Carbon $today): array
    {
        if ($releases->isEmpty()) {
            return [];
        }

        $ids = $releases->pluck('id');

        $counts = Task::counted()
            ->whereIn('release_id', $ids)
            ->selectRaw('release_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as done', ['done'])
            ->groupBy('release_id')
            ->get()->keyBy('release_id');

        // Open work still tagged with a phase, to spot phases that ran out of time.
        $openByPhase = Task::query()
            ->whereIn('release_id', $ids)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->whereNotNull('phase')
            ->selectRaw('release_id, phase, count(*) as aggregate')
            ->groupBy('release_id', 'phase')
            ->get()
            ->groupBy('release_id');

        $rows = $releases->map(function (Release $release) use ($counts, $openByPhase, $conflicts, $today) {
            $total = (int) ($counts[$release->id]->total ?? 0);
            $done = (int) ($counts[$release->id]->done ?? 0);
            $pct = Metric::pct($done, $total);

            $span = max((int) $release->start_date->diffInDays($release->end_date), 1);
            $elapsed = (int) max(0, min(100, round($release->start_date->diffInDays($today, false) / $span * 100)));

            $openPhases = collect($openByPhase->get($release->id, []))->pluck('aggregate', 'phase');
            $latePhases = $release->phases
                ->filter(fn ($phase) => $phase->end_date->lt($today) && ($openPhases[$phase->phase] ?? 0) > 0)
                ->map(fn ($phase) => $phase->label())
                ->values()->all();

            $flags = [];
            if ($conflicts[$release->id] ?? false) {
                $flags[] = 'Team double-booked';
            }
            if ($latePhases) {
                $flags[] = 'Late: '.implode(', ', $latePhases);
            }
            if ($pct !== null && $elapsed >= 20 && $elapsed - $pct >= self::BEHIND_POINTS) {
                $flags[] = 'Behind plan';
            }
            if ($release->end_date->lt($today) && $pct !== 100) {
                $flags[] = 'Past end date';
            }

            return [
                'release' => $release,
                'total' => $total,
                'done' => $done,
                'pct' => $pct,
                'elapsedPct' => $elapsed,
                'latePhases' => $latePhases,
                'flags' => $flags,
                'atRisk' => $flags !== [],
            ];
        });

        return $rows->sortBy(fn (array $r) => ($r['atRisk'] ? '0' : '1').$r['release']->end_date->toDateString())
            ->values()->all();
    }

    /**
     * Tasksheet and standup habits over the last working days, per team and
     * in total, plus today's standup coverage.
     *
     * @param  ?list<int>  $teamIds
     * @return array<string, mixed>
     */
    private function compliance(?array $teamIds, Carbon $today): array
    {
        $days = [];
        for ($day = $today->copy()->subDay(); count($days) < self::COMPLIANCE_DAYS; $day->subDay()) {
            if ($day->isWeekday()) {
                $days[] = $day->copy();
            }
        }
        $from = end($days);

        $memberships = DB::table('team_user')
            ->join('users', 'users.id', '=', 'team_user.user_id')
            ->whereNull('team_user.left_at')
            ->whereNull('users.deleted_at')
            ->whereNull('users.deactivated_at')
            ->when($teamIds !== null, fn ($q) => $q->whereIn('team_user.team_id', $teamIds))
            ->get(['team_user.team_id', 'team_user.user_id', 'team_user.created_at']);

        $teams = Team::whereIn('id', $memberships->pluck('team_id')->unique())->orderBy('name')->get(['id', 'name', 'color']);

        $filledSql = collect(TasksheetEntry::TASK_FIELDS)->map(fn ($f) => "{$f} is not null")->implode(' and ');
        $leaveTypes = TasksheetEntry::FULL_DAY_LEAVE_TYPES;
        $leaveIn = implode(',', array_fill(0, count($leaveTypes), '?'));

        $aggregate = fn (Carbon $start, Carbon $end) => TasksheetEntry::query()
            ->whereIn('team_id', $teams->pluck('id'))
            ->whereIn('user_id', $memberships->pluck('user_id')->unique())
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw(
                "team_id,
                 sum(case when ({$filledSql}) and (leave_type is null or leave_type not in ({$leaveIn})) then 1 else 0 end) as filled,
                 sum(case when leave_type in ({$leaveIn}) then 1 else 0 end) as on_leave,
                 sum(case when standup_attended is not null and (leave_type is null or leave_type not in ({$leaveIn})) then 1 else 0 end) as marked,
                 sum(case when standup_attended = 1 then 1 else 0 end) as attended",
                [...$leaveTypes, ...$leaveTypes, ...$leaveTypes]
            )
            ->groupBy('team_id')
            ->get()->keyBy('team_id');

        $window = $aggregate($from, $today->copy()->subDay());
        $todayRows = $aggregate($today, $today);

        $rows = $teams->map(function (Team $team) use ($memberships, $days, $window, $todayRows) {
            $members = $memberships->where('team_id', $team->id);
            // A member is owed a row only for weekdays on or after joining.
            $expected = $members->sum(fn ($m) => collect($days)
                ->filter(fn (Carbon $d) => $m->created_at === null || $d->gte(Carbon::parse($m->created_at)->startOfDay()))
                ->count());
            $w = $window->get($team->id);
            $owed = max($expected - (int) ($w->on_leave ?? 0), 0);

            return [
                'team' => $team,
                'members' => $members->count(),
                'filled' => (int) ($w->filled ?? 0),
                'expected' => $owed,
                'fillRate' => Metric::pct(min((int) ($w->filled ?? 0), $owed), $owed),
                'attended' => (int) ($w->attended ?? 0),
                'marked' => (int) ($w->marked ?? 0),
                'standupRate' => Metric::pct((int) ($w->attended ?? 0), (int) ($w->marked ?? 0)),
                'todayAttended' => (int) ($todayRows->get($team->id)->attended ?? 0),
                'todayMarked' => (int) ($todayRows->get($team->id)->marked ?? 0),
            ];
        })->values();

        $filled = min($rows->sum('filled'), $rows->sum('expected'));

        return [
            'days' => self::COMPLIANCE_DAYS,
            'filled' => $rows->sum('filled'),
            'expected' => $rows->sum('expected'),
            'fillRate' => Metric::pct($filled, $rows->sum('expected')),
            'attended' => $rows->sum('attended'),
            'marked' => $rows->sum('marked'),
            'standupRate' => Metric::pct($rows->sum('attended'), $rows->sum('marked')),
            'members' => $memberships->pluck('user_id')->unique()->count(),
            'todayAttended' => $rows->sum('todayAttended'),
            'teams' => $rows->all(),
        ];
    }

    /**
     * The viewer's own open work — a small personal strip for any role.
     *
     * @return array{open: int, overdue: int, dueSoon: int}
     */
    private function mine(User $user, Carbon $today): array
    {
        $row = Task::query()
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->selectRaw(
                'count(*) as open_count,
                 sum(case when due_date < ? then 1 else 0 end) as overdue,
                 sum(case when due_date >= ? and due_date <= ? then 1 else 0 end) as due_soon',
                [$today->toDateString(), $today->toDateString(), $today->copy()->addDays(self::DUE_SOON_DAYS)->toDateString()]
            )
            ->first();

        return ['open' => (int) $row->open_count, 'overdue' => (int) $row->overdue, 'dueSoon' => (int) $row->due_soon];
    }
}

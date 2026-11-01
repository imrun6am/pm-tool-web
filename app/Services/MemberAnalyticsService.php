<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\User;
use App\Support\Metric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A developer's or QA member's personal analytics: what they finished, how
 * fast work moves, whether their habits hold, and how today compares with
 * their own past — never with a colleague's.
 *
 * Tasks carry no started/completed columns, so both moments are read from the
 * RecordsActivity audit trail: *started* is the first move to In Progress,
 * *completed* the last move from an open status into Done. A task only counts
 * as completed while it is still Done, so reopened work drops out — and so
 * does archived work, which is never counted anywhere (Task::UNCOUNTED_STATUSES).
 * Tasks with no logged transition (seeded rows, or ones created already done)
 * fall back to a timestamp and are flagged `estimated`; those feed counts but
 * never cycle or lead time, which would otherwise be fiction.
 *
 * Every query here is scoped to the one user and runs once per request, so the
 * cost is fixed regardless of history. Nothing is cached: the moment a member
 * finishes a task is the moment the page should reflect it.
 *
 * The tasksheet's lead-only `feedback` column is never selected.
 */
class MemberAnalyticsService
{
    /** Weeks shown on the completion and lead-time trends. */
    public const TREND_WEEKS = 12;

    /** Weeks shown on the contribution heatmap (and the streak's horizon). */
    public const HEATMAP_WEEKS = 26;

    /** Full weeks averaged into the member's usual weekly pace. */
    public const PACE_WEEKS = 8;

    /** How far ahead a due date counts as "due soon". */
    public const DUE_SOON_DAYS = 7;

    public const FOCUS_LIMIT = 6;

    public const RECENT_LIMIT = 5;

    /** Tasksheet columns read here — deliberately excludes `feedback`. */
    private const ENTRY_COLUMNS = [
        'id', 'team_id', 'user_id', 'date', 'plan', 'result', 'comment', 'tickets',
        'work_points', 'ticket_count', 'ticket_points', 'leave_type', 'standup_attended',
    ];

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, MemberPeriod $period): array
    {
        $today = $period->end->copy()->startOfDay();
        $thisWeek = $today->copy()->startOfWeek();
        $heatmapStart = $thisWeek->copy()->subWeeks(self::HEATMAP_WEEKS - 1);
        $windowStart = $heatmapStart->copy()->min($period->previousStart);

        $tasks = Task::query()
            ->with('release')
            ->where('assignee_id', $user->id)
            ->get();

        $completions = $this->completions($user, $tasks);
        $days = $this->days($user, $windowStart, $today, $completions);
        $joinedAt = $this->joinedTeamsAt($user);

        $open = $tasks->reject(fn (Task $t) => in_array($t->status, Task::DONE_STATUSES, true));

        $weekly = $this->weekly($completions, $thisWeek);

        return [
            'period' => $period,
            'today' => [
                'open' => $open->count(),
                'overdue' => $open->filter(fn (Task $t) => $t->due_date?->lt($today))->count(),
                'dueSoon' => $open->filter(fn (Task $t) => $t->due_date
                    && $t->due_date->betweenIncluded($today, $today->copy()->addDays(self::DUE_SOON_DAYS)))->count(),
                'inProgress' => $open->where('status', 'in_progress')->count(),
                'inReview' => $open->where('status', 'in_review')->count(),
            ],
            'focus' => $this->focus($open, $today),
            'kpis' => $this->kpis($period, $completions, $days, $joinedAt, $today),
            'weekly' => $weekly,
            'bestWeek' => $this->bestWeek($completions),
            'pace' => $this->pace($completions, $thisWeek),
            'statusCounts' => $this->statusCounts($tasks),
            'taskTotal' => $tasks->whereNotIn('status', Task::UNCOUNTED_STATUSES)->count(),
            'heatmap' => $this->heatmap($days, $heatmapStart, $today),
            'streak' => $this->streak($days, $heatmapStart, $today),
            'leadTimeTrend' => $this->leadTimeTrend($completions, $thisWeek),
            'recent' => $completions->sortByDesc(fn (array $c) => $c['completedAt']->getTimestamp())
                ->take(self::RECENT_LIMIT)
                ->map(fn (array $c) => ['task' => $c['task'], 'completedAt' => $c['completedAt']])
                ->values()->all(),
            'onTeam' => $joinedAt !== null,
            'hasHistory' => $tasks->isNotEmpty() || $days->contains(fn (array $d) => $d['entries']->isNotEmpty() || $d['comments'] > 0),
        ];
    }

    /**
     * Every currently-done task with when it was started and completed.
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, array{task: Task, startedAt: ?Carbon, completedAt: Carbon, estimated: bool}>
     */
    private function completions(User $user, Collection $tasks): Collection
    {
        // One query for every status change on the member's tasks; the id list
        // is a sub-select so a long history never becomes a huge IN binding.
        $history = Activity::query()
            ->where('subject_type', (new Task)->getMorphClass())
            ->whereIn('subject_id', Task::select('id')->where('assignee_id', $user->id))
            ->whereIn('event', ['created', 'updated'])
            ->whereNotNull('properties->attributes->status')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'subject_id', 'event', 'properties', 'created_at'])
            ->groupBy('subject_id');

        return $tasks
            ->filter(fn (Task $t) => $t->status === 'done')
            ->map(function (Task $task) use ($history) {
                $startedAt = null;
                $completedAt = null;

                foreach ($history->get($task->id, []) as $activity) {
                    $new = $activity->properties['attributes']['status'] ?? null;
                    $old = $activity->event === 'created' ? null : ($activity->properties['old']['status'] ?? null);

                    if ($new === 'in_progress' && $startedAt === null) {
                        $startedAt = $activity->created_at;
                    }
                    // Only a move into Done completes; Archive → Done is a restore.
                    if ($new === 'done' && ! in_array($old, Task::DONE_STATUSES, true)) {
                        $completedAt = $activity->created_at;
                    }
                }

                return [
                    'task' => $task,
                    'startedAt' => $startedAt && $completedAt && $startedAt->lte($completedAt) ? $startedAt : null,
                    'completedAt' => $completedAt ?? $task->updated_at ?? $task->created_at,
                    'estimated' => $completedAt === null,
                ];
            })
            ->values();
    }

    /**
     * One row per calendar day of the window: tasks completed, comments
     * written, tasksheet rows, and whether the day was logged or on leave.
     *
     * @param  Collection<int, array<string, mixed>>  $completions
     * @return Collection<string, array<string, mixed>>
     */
    private function days(User $user, Carbon $from, Carbon $to, Collection $completions): Collection
    {
        $entries = TasksheetEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get(self::ENTRY_COLUMNS)
            ->groupBy(fn (TasksheetEntry $e) => $e->date->toDateString());

        $comments = Comment::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, count(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $meetings = Event::query()
            ->where('type', 'meeting')
            ->whereBetween('starts_at', [$from, now()])
            ->whereHas('attendees', fn ($q) => $q->whereKey($user->id))
            ->pluck('starts_at')
            ->countBy(fn ($at) => Carbon::parse($at)->toDateString());

        $done = $completions->countBy(fn (array $c) => $c['completedAt']->toDateString());

        $days = collect();
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $dayEntries = $entries->get($key, collect());

            $days[$key] = [
                'date' => $day->copy(),
                'tasks' => (int) ($done[$key] ?? 0),
                'comments' => (int) ($comments[$key] ?? 0),
                'meetings' => (int) ($meetings[$key] ?? 0),
                'entries' => $dayEntries,
                'logged' => $dayEntries->contains(fn (TasksheetEntry $e) => $e->isFullyFilled()),
                'leave' => $dayEntries->contains(fn (TasksheetEntry $e) => $e->isFullDayLeave()),
            ];
        }

        return $days;
    }

    /**
     * When the member joined their current teams, or null when they are on
     * none — someone off every team has no tasksheet to fill.
     */
    private function joinedTeamsAt(User $user): ?Carbon
    {
        $row = DB::table('team_user')
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->selectRaw('count(*) as memberships, min(created_at) as joined')
            ->first();

        if (! $row || (int) $row->memberships === 0) {
            return null;
        }

        return ($row->joined ? Carbon::parse($row->joined) : $user->created_at ?? Carbon::today())->startOfDay();
    }

    /**
     * Overdue first (oldest due first), then due today, then in progress.
     *
     * @param  Collection<int, Task>  $open
     * @return list<array{task: Task, reason: string}>
     */
    private function focus(Collection $open, Carbon $today): array
    {
        $overdue = $open->filter(fn (Task $t) => $t->due_date?->lt($today))
            ->sortBy(fn (Task $t) => $t->due_date->toDateString().'#'.$t->id)
            ->map(fn (Task $t) => ['task' => $t, 'reason' => 'overdue']);

        $dueToday = $open->filter(fn (Task $t) => $t->due_date?->isSameDay($today))
            ->sortBy('id')
            ->map(fn (Task $t) => ['task' => $t, 'reason' => 'due_today']);

        $taken = $overdue->pluck('task.id')->merge($dueToday->pluck('task.id'));

        $inProgress = $open->where('status', 'in_progress')
            ->reject(fn (Task $t) => $taken->contains($t->id))
            ->sortBy(fn (Task $t) => ($t->due_date?->toDateString() ?? '9999-12-31').'#'.$t->id)
            ->map(fn (Task $t) => ['task' => $t, 'reason' => 'in_progress']);

        return $overdue->concat($dueToday)->concat($inProgress)
            ->take(self::FOCUS_LIMIT)->values()->all();
    }

    /**
     * Headline numbers for the period, each beside the same elapsed span of
     * the previous one.
     *
     * @param  Collection<int, array<string, mixed>>  $completions
     * @param  Collection<string, array<string, mixed>>  $days
     * @return array<string, array{current: int|float|null, previous: int|float|null, change: int|float|null}>
     */
    private function kpis(MemberPeriod $period, Collection $completions, Collection $days, ?Carbon $joinedAt, Carbon $today): array
    {
        $current = $this->spanStats($period->start, $period->end, $completions, $days, $joinedAt, $today);
        $previous = $this->spanStats($period->previousStart, $period->previousEnd, $completions, $days, $joinedAt, $today);

        $kpis = [];
        foreach ($current as $key => $value) {
            $kpis[$key] = Metric::compare($value, $previous[$key]);
        }

        return $kpis;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $completions
     * @param  Collection<string, array<string, mixed>>  $days
     * @return array<string, int|float|null>
     */
    private function spanStats(Carbon $from, Carbon $to, Collection $completions, Collection $days, ?Carbon $joinedAt, Carbon $today): array
    {
        $done = $completions->filter(fn (array $c) => $c['completedAt']->betweenIncluded($from, $to));

        $dated = $done->filter(fn (array $c) => $c['task']->due_date !== null);
        $onTime = $dated->filter(fn (array $c) => $c['completedAt']->toDateString() <= $c['task']->due_date->toDateString());

        $cycles = $done->filter(fn (array $c) => ! $c['estimated'] && $c['startedAt'])
            ->map(fn (array $c) => $c['startedAt']->diffInSeconds($c['completedAt']) / 86400);
        $leads = $done->reject(fn (array $c) => $c['estimated'])
            ->map(fn (array $c) => $c['task']->created_at->diffInSeconds($c['completedAt']) / 86400);

        $span = $days->filter(fn (array $d) => $d['date']->betweenIncluded($from->copy()->startOfDay(), $to));
        $entries = $span->flatMap(fn (array $d) => $d['entries']);

        // Standups: of the rows a lead marked, how many were attended.
        $marked = $entries->filter(fn (TasksheetEntry $e) => $e->standup_attended !== null && ! $e->isFullDayLeave());
        $attended = $marked->filter(fn (TasksheetEntry $e) => $e->attendedStandup());

        // Tasksheet: weekdays since joining a team, up to yesterday — plus today
        // once it is logged, so an unfilled morning never reads as a miss.
        $expected = $joinedAt === null ? collect() : $span->filter(fn (array $d) => $d['date']->isWeekday()
            && $d['date']->gte($joinedAt)
            && ! $d['leave']
            && ($d['date']->lt($today) || $d['logged']));
        $logged = $expected->where('logged', true);

        return [
            'completed' => $done->count(),
            'onTimeRate' => Metric::pct($onTime->count(), $dated->count()),
            'cycleDays' => $cycles->isEmpty() ? null : round($cycles->avg(), 1),
            'leadDays' => $leads->isEmpty() ? null : round($leads->avg(), 1),
            'tickets' => (int) $entries->sum('ticket_count'),
            'ticketPoints' => (int) $entries->sum('ticket_points'),
            'workPoints' => (int) $entries->sum('work_points'),
            'comments' => (int) $span->sum('comments'),
            'meetings' => (int) $span->sum('meetings'),
            'standupAttended' => $attended->count(),
            'standupMarked' => $marked->count(),
            'standupRate' => Metric::pct($attended->count(), $marked->count()),
            'daysLogged' => $logged->count(),
            'daysExpected' => $expected->count(),
            'tasksheetRate' => Metric::pct($logged->count(), $expected->count()),
        ];
    }

    /**
     * Completions per week for the trend, oldest first, flagging the busiest.
     *
     * @param  Collection<int, array<string, mixed>>  $completions
     * @return list<array{weekStart: Carbon, count: int, current: bool, best: bool}>
     */
    private function weekly(Collection $completions, Carbon $thisWeek): array
    {
        $weeks = [];
        for ($i = self::TREND_WEEKS - 1; $i >= 0; $i--) {
            $start = $thisWeek->copy()->subWeeks($i);
            $weeks[] = [
                'weekStart' => $start,
                'count' => $this->countBetween($completions, $start, $start->copy()->endOfWeek()),
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
     * The member's all-time busiest week.
     *
     * @param  Collection<int, array<string, mixed>>  $completions
     * @return array{weekStart: ?Carbon, count: int}
     */
    private function bestWeek(Collection $completions): array
    {
        $byWeek = $completions->countBy(fn (array $c) => $c['completedAt']->copy()->startOfWeek()->toDateString());

        if ($byWeek->isEmpty()) {
            return ['weekStart' => null, 'count' => 0];
        }

        $max = $byWeek->max();
        $week = $byWeek->filter(fn (int $n) => $n === $max)->keys()->sort()->last();

        return ['weekStart' => Carbon::parse($week), 'count' => $max];
    }

    /**
     * This week against the member's own norm: the ceiling of their average
     * over the previous full weeks. No history, no goal — it is never a number
     * someone else picked.
     *
     * @param  Collection<int, array<string, mixed>>  $completions
     * @return array{thisWeek: int, goal: ?int, pct: ?int}
     */
    private function pace(Collection $completions, Carbon $thisWeek): array
    {
        $done = $this->countBetween($completions, $thisWeek, $thisWeek->copy()->endOfWeek());
        $past = $this->countBetween(
            $completions,
            $thisWeek->copy()->subWeeks(self::PACE_WEEKS),
            $thisWeek->copy()->subSecond()
        );

        $goal = $past > 0 ? max(1, (int) ceil($past / self::PACE_WEEKS)) : null;

        return [
            'thisWeek' => $done,
            'goal' => $goal,
            'pct' => $goal ? min(100, (int) round($done / $goal * 100)) : null,
        ];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<string, int>
     */
    private function statusCounts(Collection $tasks): array
    {
        $raw = $tasks->countBy('status');

        $counts = [];
        foreach (Task::countedStatuses() as $status) {
            $counts[$status] = (int) ($raw[$status] ?? 0);
        }

        return $counts;
    }

    /**
     * Monday-first week columns of daily contribution counts.
     *
     * @param  Collection<string, array<string, mixed>>  $days
     * @return array{weeks: list<list<array<string, mixed>>>, total: int, activeDays: int}
     */
    private function heatmap(Collection $days, Carbon $from, Carbon $today): array
    {
        $weeks = [];
        $total = 0;
        $active = 0;

        for ($w = 0; $w < self::HEATMAP_WEEKS; $w++) {
            $column = [];
            for ($d = 0; $d < 7; $d++) {
                $date = $from->copy()->addWeeks($w)->addDays($d);
                $day = $days->get($date->toDateString());
                $count = $day ? $day['tasks'] + $day['comments'] + ($day['logged'] ? 1 : 0) : 0;

                $total += $count;
                $active += $count > 0 ? 1 : 0;

                $column[] = [
                    'date' => $date,
                    'count' => $count,
                    'tasks' => $day['tasks'] ?? 0,
                    'comments' => $day['comments'] ?? 0,
                    'logged' => $day['logged'] ?? false,
                    'level' => match (true) {
                        $count === 0 => 0,
                        $count === 1 => 1,
                        $count <= 3 => 2,
                        $count <= 5 => 3,
                        default => 4,
                    },
                    'future' => $date->gt($today),
                ];
            }
            $weeks[] = $column;
        }

        return ['weeks' => $weeks, 'total' => $total, 'activeDays' => $active];
    }

    /**
     * Consecutive active days — a completed task or a fully logged tasksheet.
     * Weekends and full-day leave never break a run (they extend it if worked),
     * and today only counts once it is active.
     *
     * @param  Collection<string, array<string, mixed>>  $days
     * @return array{current: int, best: int, activeToday: bool}
     */
    private function streak(Collection $days, Carbon $from, Carbon $today): array
    {
        $window = $days->filter(fn (array $d) => $d['date']->gte($from))->values();
        $active = fn (array $d) => $d['tasks'] > 0 || $d['logged'];
        $pause = fn (array $d) => $d['date']->isWeekend() || $d['leave'] || $d['date']->isSameDay($today);

        $current = 0;
        foreach ($window->reverse() as $day) {
            if ($active($day)) {
                $current++;
            } elseif (! $pause($day)) {
                break;
            }
        }

        $best = 0;
        $run = 0;
        foreach ($window as $day) {
            if ($active($day)) {
                $best = max($best, ++$run);
            } elseif (! $pause($day)) {
                $run = 0;
            }
        }

        $last = $window->last();

        return ['current' => $current, 'best' => $best, 'activeToday' => $last ? $active($last) : false];
    }

    /**
     * Average days from creation to completion, per week.
     *
     * @param  Collection<int, array<string, mixed>>  $completions
     * @return list<array{weekStart: Carbon, days: ?float}>
     */
    private function leadTimeTrend(Collection $completions, Carbon $thisWeek): array
    {
        $trend = [];
        for ($i = self::TREND_WEEKS - 1; $i >= 0; $i--) {
            $start = $thisWeek->copy()->subWeeks($i);
            $end = $start->copy()->endOfWeek();

            $leads = $completions
                ->filter(fn (array $c) => ! $c['estimated'] && $c['completedAt']->betweenIncluded($start, $end))
                ->map(fn (array $c) => $c['task']->created_at->diffInSeconds($c['completedAt']) / 86400);

            $trend[] = ['weekStart' => $start, 'days' => $leads->isEmpty() ? null : round($leads->avg(), 1)];
        }

        return $trend;
    }

    /** @param  Collection<int, array<string, mixed>>  $completions */
    private function countBetween(Collection $completions, Carbon $from, Carbon $to): int
    {
        return $completions->filter(fn (array $c) => $c['completedAt']->betweenIncluded($from, $to))->count();
    }
}

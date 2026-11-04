<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AiUsageLog;
use App\Models\Comment;
use App\Models\DailyStandup;
use App\Models\Event;
use App\Models\KpiReport;
use App\Models\KpiReportMember;
use App\Models\MeetingNote;
use App\Models\PerformanceScore;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\TriageItem;
use App\Models\User;
use App\Services\Kpi\KpiReviewService;
use App\Support\LogRetention;
use App\Support\Metric;
use App\Support\WorkingWeek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the app holds about how a team or a person worked over a period,
 * for admins. Nothing is combined into a score.
 *
 * Each kind of fact is loaded once for the period and added up here, so the
 * query count doesn't grow with the head count. Facts are kept as countable
 * parts per person per team, and rates are worked out last from summed parts,
 * so a team's rate is never an average of its members' rates.
 *
 * Every task, time entry and request belongs to one team: its own, else the
 * person's team if they're on exactly one, else "No team". So team rows add up
 * to the total, and a person on two teams isn't counted twice. Calendar
 * meetings, meeting notes, comments and AI uses can't be split that way, so
 * they stay whole-person; a team row sums them over its members.
 *
 * Tasksheet feedback and rating notes are never read, and private events are
 * left out: they're only for their creator and attendees.
 */
class PeopleAnalyticsService
{
    /** Tasks needed before a rate or a duration means anything. */
    public const MIN_TASKS = 3;

    /** Tasksheet days, or marked standups, needed before a rate means anything. */
    public const MIN_DAYS = 5;

    /** The bucket for work with no team to belong to. */
    public const NO_TEAM = 0;

    /** Longest period shown by the week; longer ones go by the month. */
    private const WEEKLY_MAX_DAYS = 112;

    /** Rows of each task list on a person's page. The CSV has them all. */
    public const TASK_LIST_LIMIT = 25;

    public function __construct(
        private readonly TaskCompletions $completions,
        private readonly PerformanceAnalytics $performance,
    ) {}

    /**
     * Quick ranges for the filter bar, newest first.
     *
     * @return array<string, array{from: Carbon, to: Carbon}>
     */
    public static function presets(): array
    {
        $today = today();

        return [
            'Last 30 days' => ['from' => $today->copy()->subDays(29), 'to' => $today->copy()],
            'This month' => ['from' => $today->copy()->startOfMonth(), 'to' => $today->copy()],
            'Last month' => ['from' => $today->copy()->subMonthNoOverflow()->startOfMonth(), 'to' => $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'This quarter' => ['from' => $today->copy()->firstOfQuarter(), 'to' => $today->copy()],
        ];
    }

    /**
     * The whole report: a summary, one row per team, one row per person.
     *
     * @param  array{from: Carbon, to: Carbon, team_id: ?int}  $filters
     * @return array<string, mixed>
     */
    public function report(array $filters): array
    {
        $teamId = $filters['team_id'];
        $facts = $this->facts($filters['from'], $filters['to']);
        $people = $this->peopleRows($facts, $teamId);

        $parts = $this->merge(array_column($people, 'parts'));
        $whole = $this->mergeWhole(array_column($people, 'whole'));
        $summary = $this->record($parts, $whole, $this->averageRating($people), $this->sumKpi($people));

        $previous = $this->previousFilters($filters);
        $before = $this->facts($previous['from'], $previous['to'], light: true);
        $beforeRows = $this->peopleRows($before, $teamId);
        $previousSummary = $this->record(
            $this->merge(array_column($beforeRows, 'parts')),
            $this->mergeWhole(array_column($beforeRows, 'whole')),
            $this->averageRating($beforeRows),
            $this->sumKpi($beforeRows),
        );

        return [
            'from' => $filters['from'],
            'to' => $filters['to'],
            'teamId' => $teamId,
            'team' => $teamId ? $facts['teams']->get($teamId) : null,
            'previous' => $previous,
            'summary' => $summary,
            'compare' => $this->compare($summary, $previousSummary),
            'teams' => $teamId ? [] : $this->teamRows($facts),
            'people' => array_map($this->withoutParts(...), $people),
            'trend' => $this->trend($facts, $filters['from'], $filters['to'], $teamId),
            'workingDays' => count($facts['workingDays']),
            'blockedHistoryFrom' => $this->blockedHistoryFrom($filters['from']),
        ];
    }

    /**
     * One person: the same figures, the split by team, and the period before.
     *
     * @param  array{from: Carbon, to: Carbon, team_id: ?int}  $filters
     * @return array<string, mixed>
     */
    public function person(User $user, array $filters): array
    {
        $teamId = $filters['team_id'];
        $facts = $this->facts($filters['from'], $filters['to'], $user->id);
        $row = $this->personRow($facts, $user, $teamId);

        $previous = $this->previousFilters($filters);
        $before = $this->facts($previous['from'], $previous['to'], $user->id, light: true);
        $beforeRow = $this->personRow($before, $user, $teamId);

        $finished = $this->finishedTasks($facts, $teamId);
        $open = $this->openTasks($facts, $user, $teamId);

        return [
            'from' => $filters['from'],
            'to' => $filters['to'],
            'teamId' => $teamId,
            'team' => $teamId ? $facts['teams']->get($teamId) : null,
            'previous' => $previous,
            'user' => $user,
            'teams' => $row['teams'],
            'record' => $row['record'],
            'categories' => $row['categories'],
            'compare' => $this->compare($row['record'], $beforeRow['record']),
            'byTeam' => $teamId ? [] : $this->personByTeam($facts, $user),
            'trend' => $this->trend($facts, $filters['from'], $filters['to'], $teamId, $user->id),
            'finished' => $finished,
            'open' => $open,
            'workingDays' => count($facts['workingDays']),
            'blockedHistoryFrom' => $this->blockedHistoryFrom($filters['from']),
        ];
    }

    /** @return array{from: Carbon, to: Carbon, team_id: ?int} the same length of time, just before */
    private function previousFilters(array $filters): array
    {
        $days = (int) $filters['from']->diffInDays($filters['to']) + 1;

        return [
            'from' => $filters['from']->copy()->subDays($days),
            'to' => $filters['from']->copy()->subDay(),
            'team_id' => $filters['team_id'],
        ];
    }

    /** The earliest day blocked time can be seen, when the period reaches back further. */
    private function blockedHistoryFrom(Carbon $from): ?Carbon
    {
        $cutoff = LogRetention::cutoff();

        return $from->lt($cutoff) ? $cutoff : null;
    }

    // ---- facts --------------------------------------------------------------

    /**
     * Every fact in the period, once. Pass a user to read only theirs. `light`
     * skips what only the current period shows.
     *
     * @return array<string, mixed>
     */
    private function facts(Carbon $from, Carbon $to, ?int $userId = null, bool $light = false): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $workingDays = $this->workingDays($from, $to);

        $memberships = DB::table('team_user')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->where(fn ($q) => $q->whereNull('created_at')->orWhere('created_at', '<=', $end))
            ->where(fn ($q) => $q->whereNull('left_at')->orWhere('left_at', '>=', $start))
            ->get(['team_id', 'user_id', 'created_at', 'left_at']);

        $facts = [
            'from' => $start,
            'to' => $end,
            'workingDays' => $workingDays,
            'memberships' => $memberships,
            'membershipsByUser' => $memberships->groupBy('user_id'),
            'membershipsByTeam' => $memberships->groupBy('team_id'),
            'singleTeam' => $this->singleTeams($memberships),
            'teams' => Team::with('teamLead:id,name')->orderBy('name')->get()->keyBy('id'),
            'parts' => [],
            'sheet' => [],
            'whole' => [],
            'scores' => [],
            'kpi' => [],
            'completions' => collect(),
            'hoursByDay' => [],
        ];

        $this->addCompletions($facts, $start, $end, $userId);
        $this->addOpenWork($facts, $userId);
        $this->addBlockedTime($facts, $start, $end, $userId);
        $this->addTimeLogs($facts, $from, $to, $userId);
        $this->addRequests($facts, $start, $end, $userId);
        $this->addTasksheet($facts, $from, $to, $userId);

        if (! $light) {
            $this->addMeetings($facts, $start, $end, $userId);
            $this->addCollaboration($facts, $from, $to, $start, $end, $userId);
            $this->addKpi($facts, $from, $to, $userId);
        }
        $this->addRatings($facts, $from, $to, $userId);

        $facts['users'] = $this->users($facts);

        return $facts;
    }

    /** @return list<string> working days in the period, as Y-m-d */
    private function workingDays(Carbon $from, Carbon $to): array
    {
        $days = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            if (WorkingWeek::isWorkingDay($day)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    /**
     * The team of each person on exactly one, for work with no team of its own.
     *
     * @return array<int, int> user id => team id
     */
    private function singleTeams(Collection $memberships): array
    {
        return $memberships->groupBy('user_id')
            ->filter(fn (Collection $rows) => $rows->pluck('team_id')->unique()->count() === 1)
            ->map(fn (Collection $rows) => (int) $rows->first()->team_id)
            ->all();
    }

    /** Which team a piece of someone's work belongs to. */
    private function bucket(array $facts, ?int $teamId, ?int $userId): int
    {
        return $teamId ?: ($facts['singleTeam'][$userId] ?? self::NO_TEAM);
    }

    private function addCompletions(array &$facts, Carbon $start, Carbon $end, ?int $userId): void
    {
        $completions = $this->completions
            ->since(fn (): Builder => Task::query()
                ->whereNotNull('assignee_id')
                ->when($userId, fn ($q) => $q->where('assignee_id', $userId)), $start)
            ->filter(fn (array $c) => $c['completedAt']->lte($end))
            ->values();

        $estimated = $completions->filter(fn (array $c) => $c['estimateMinutes'] > 0);
        $logged = $estimated->isEmpty() ? collect() : TaskTimeLog::query()
            ->whereIn('task_id', $estimated->pluck('taskId'))
            ->selectRaw('task_id, sum(minutes) as minutes')
            ->groupBy('task_id')
            ->pluck('minutes', 'task_id');

        $kept = [];
        foreach ($completions as $c) {
            $uid = (int) $c['assigneeId'];
            $bucket = $this->bucket($facts, $c['teamId'], $uid);
            $parts = &$this->parts($facts, $uid, $bucket);

            $parts['completed']++;
            if ($c['reworked']) {
                $parts['reworked']++;
            }
            if ($c['dueDate'] !== null) {
                $parts['dated']++;
                if ($c['completedAt']->toDateString() <= $c['dueDate']->toDateString()) {
                    $parts['onTime']++;
                }
            }
            if (! $c['estimated']) {
                $parts['leads'][] = $c['createdAt']->diffInSeconds($c['completedAt']) / 86400;
                if ($c['startedAt'] !== null) {
                    $parts['cycles'][] = $c['startedAt']->diffInSeconds($c['completedAt']) / 86400;
                }
            }

            // The same rule as the time report: estimated, and with time logged.
            $minutes = (int) ($logged[$c['taskId']] ?? 0);
            if ($c['estimateMinutes'] > 0 && $minutes > 0) {
                $parts['comparedTasks']++;
                $parts['estimatedMinutes'] += $c['estimateMinutes'];
                $parts['comparedLogged'] += $minutes;
            }
            unset($parts);

            $kept[] = $c + ['bucket' => $bucket];
        }

        $facts['completions'] = collect($kept);
    }

    /** Open work is as of now: the board keeps only the current status. */
    private function addOpenWork(array &$facts, ?int $userId): void
    {
        $open = Task::query()
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->whereNotNull('assignee_id')
            ->when($userId, fn ($q) => $q->where('assignee_id', $userId))
            ->selectRaw(
                'assignee_id, team_id, status, count(*) as total,'
                .' sum(case when due_date < ? then 1 else 0 end) as overdue,'
                .' sum(case when estimate_minutes is null then 1 else 0 end) as unestimated',
                [today()->toDateString()]
            )
            ->groupBy('assignee_id', 'team_id', 'status')
            ->toBase()
            ->get();

        $statusPart = ['todo' => 'todo', 'in_progress' => 'inProgress', 'in_review' => 'inReview', 'recheck' => 'recheck'];
        foreach ($open as $row) {
            $parts = &$this->parts($facts, (int) $row->assignee_id, $this->bucket($facts, $row->team_id ? (int) $row->team_id : null, (int) $row->assignee_id));
            $parts['open'] += (int) $row->total;
            $parts['overdue'] += (int) $row->overdue;
            $parts['unestimated'] += (int) $row->unestimated;
            if (isset($statusPart[$row->status])) {
                $parts[$statusPart[$row->status]] += (int) $row->total;
            }
            unset($parts);
        }

        $blocked = Task::query()
            ->blocked()
            ->whereNotNull('assignee_id')
            ->when($userId, fn ($q) => $q->where('assignee_id', $userId))
            ->selectRaw('assignee_id, team_id, count(*) as total')
            ->groupBy('assignee_id', 'team_id')
            ->toBase()
            ->get();

        foreach ($blocked as $row) {
            $parts = &$this->parts($facts, (int) $row->assignee_id, $this->bucket($facts, $row->team_id ? (int) $row->team_id : null, (int) $row->assignee_id));
            $parts['blockedNow'] += (int) $row->total;
            unset($parts);
        }
    }

    /**
     * Time spent flagged as blocked, clipped to the period. A cleared flag leaves
     * a complete interval in the activity log (the old value, and when it
     * changed), and tasks still flagged supply the open ones. Waiting on another
     * task keeps no history, so it can't count here.
     */
    private function addBlockedTime(array &$facts, Carbon $start, Carbon $end, ?int $userId): void
    {
        $until = $end->copy()->min(now());
        $intervals = [];

        $cleared = Activity::query()
            ->where('subject_type', (new Task)->getMorphClass())
            ->where('event', 'updated')
            ->where('created_at', '>=', $start)
            ->whereJsonContainsKey('properties->attributes->blocked_at')
            ->get(['subject_id', 'properties', 'created_at']);

        foreach ($cleared as $activity) {
            $was = $activity->properties['old']['blocked_at'] ?? null;
            $now = $activity->properties['attributes']['blocked_at'] ?? null;
            if ($was !== null && $now === null) {
                $intervals[] = [(int) $activity->subject_id, Carbon::parse($was), $activity->created_at];
            }
        }

        $flagged = Task::query()
            ->whereNotNull('blocked_at')
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->whereNotNull('assignee_id')
            ->when($userId, fn ($q) => $q->where('assignee_id', $userId))
            ->get(['id', 'blocked_at']);

        foreach ($flagged as $task) {
            $intervals[] = [$task->id, $task->blocked_at, $until];
        }

        if ($intervals === []) {
            return;
        }

        $tasks = Task::query()
            ->whereKey(array_unique(array_column($intervals, 0)))
            ->whereNotNull('assignee_id')
            ->when($userId, fn ($q) => $q->where('assignee_id', $userId))
            ->get(['id', 'assignee_id', 'team_id'])
            ->keyBy('id');

        foreach ($intervals as [$taskId, $began, $ended]) {
            $task = $tasks->get($taskId);
            if ($task === null) {
                continue;
            }

            $seconds = $began->copy()->max($start)->diffInSeconds($ended->copy()->min($until), false);
            if ($seconds > 0) {
                $parts = &$this->parts($facts, $task->assignee_id, $this->bucket($facts, $task->team_id, $task->assignee_id));
                $parts['blockedSeconds'] += (int) $seconds;
                unset($parts);
            }
        }
    }

    private function addTimeLogs(array &$facts, Carbon $from, Carbon $to, ?int $userId): void
    {
        $logs = TaskTimeLog::query()
            ->join('tasks', 'tasks.id', '=', 'task_time_logs.task_id')
            ->whereNull('tasks.deleted_at')
            ->whereDate('task_time_logs.logged_on', '>=', $from->toDateString())
            ->whereDate('task_time_logs.logged_on', '<=', $to->toDateString())
            ->when($userId, fn ($q) => $q->where('task_time_logs.user_id', $userId))
            ->selectRaw('task_time_logs.user_id as user_id, tasks.team_id as team_id, task_time_logs.logged_on as logged_on, sum(task_time_logs.minutes) as minutes')
            ->groupBy('task_time_logs.user_id', 'tasks.team_id', 'task_time_logs.logged_on')
            ->toBase()
            ->get();

        foreach ($logs as $row) {
            $uid = (int) $row->user_id;
            $bucket = $this->bucket($facts, $row->team_id ? (int) $row->team_id : null, $uid);
            $parts = &$this->parts($facts, $uid, $bucket);
            $parts['loggedMinutes'] += (int) $row->minutes;
            unset($parts);

            $facts['hoursByDay'][] = [
                'date' => substr((string) $row->logged_on, 0, 10),
                'bucket' => $bucket,
                'userId' => $uid,
                'minutes' => (int) $row->minutes,
            ];
        }
    }

    private function addRequests(array &$facts, Carbon $start, Carbon $end, ?int $userId): void
    {
        $items = TriageItem::query()
            ->where(fn ($q) => $q->whereBetween('created_at', [$start, $end])->orWhereBetween('decided_at', [$start, $end]))
            ->when($userId, fn ($q) => $q->where(fn ($w) => $w->where('reporter_id', $userId)->orWhere('decided_by', $userId)))
            ->get(['id', 'reporter_id', 'team_id', 'status', 'created_at', 'decided_at', 'decided_by']);

        $statusPart = [TriageItem::ACCEPTED => 'accepted', TriageItem::DECLINED => 'declined', TriageItem::PENDING => 'pending'];

        foreach ($items as $item) {
            $reporter = $item->reporter_id;
            if ($reporter && (! $userId || $reporter === $userId) && $item->created_at->betweenIncluded($start, $end)) {
                $parts = &$this->parts($facts, $reporter, $this->bucket($facts, $item->team_id, $reporter));
                $parts['raised']++;
                if (isset($statusPart[$item->status])) {
                    $parts[$statusPart[$item->status]]++;
                }
                unset($parts);
            }

            $decider = $item->decided_by ? (int) $item->decided_by : null;
            if ($decider && (! $userId || $decider === $userId) && $item->decided_at?->betweenIncluded($start, $end)) {
                $parts = &$this->parts($facts, $decider, $this->bucket($facts, $item->team_id, $decider));
                $parts['decided']++;
                unset($parts);
            }
        }
    }

    /**
     * Tasksheet habits, per team and per person. Each team has its own sheet, so
     * a person on two owes a row on each; across teams a calendar day counts
     * once, and any filled row fills it. Rich text is reduced to filled-or-empty
     * in the query, so a year of rows doesn't load everyone's HTML, and the
     * entry model still decides what counts as filled.
     */
    private function addTasksheet(array &$facts, Carbon $from, Carbon $to, ?int $userId): void
    {
        $flag = function (string $column) {
            $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

            return DB::raw("case when coalesce({$wrapped}, '') <> '' then '1' end as {$wrapped}");
        };

        $rows = TasksheetEntry::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->select([
                'user_id', 'team_id', 'date', 'work_points', 'ticket_count', 'ticket_points',
                'leave_type', 'standup_attended', 'created_at',
                $flag('plan'), $flag('result'), $flag('comment'), $flag('tickets'),
            ])
            ->cursor();

        $days = [];
        foreach ($rows as $entry) {
            $parts = &$this->parts($facts, $entry->user_id, $entry->team_id);
            $date = $entry->date->toDateString();
            $filled = $entry->isFullyFilled();

            $days[$entry->user_id][$entry->team_id][$date] = [
                'filled' => $filled,
                'fullLeave' => $entry->isFullDayLeave(),
                'halfDay' => $entry->isHalfDay(),
            ];

            if (! $entry->isFullDayLeave()) {
                $parts['workPoints'] += (int) $entry->work_points;
                $parts['tickets'] += (int) $entry->ticket_count;
                $parts['ticketPoints'] += (int) $entry->ticket_points;
                $parts['partial'] += $entry->isPartiallyFilled() ? 1 : 0;
                if (filled($entry->plan)) {
                    $parts['planned']++;
                    $parts['planWithResult'] += filled($entry->result) ? 1 : 0;
                }
                if ($entry->filledFieldCount() > 0 && $entry->wasFilledLate()) {
                    $parts['late']++;
                }
                if ($entry->standup_attended !== null) {
                    $parts['standupMarked']++;
                    $parts['standupAttended'] += $entry->attendedStandup() ? 1 : 0;
                }
            }
            unset($parts);
        }

        $this->addDaysOwed($facts, $days, $userId);
    }

    /** Working days owed and hours available, from each membership's span. */
    private function addDaysOwed(array &$facts, array $days, ?int $userId): void
    {
        $userIds = $facts['memberships']->pluck('user_id')->unique();
        $people = User::withTrashed()->whereKey($userIds)->get(['id', 'role', 'deactivated_at', 'deleted_at'])->keyBy('id');
        $today = today()->toDateString();
        $covered = [];

        foreach ($facts['memberships'] as $membership) {
            $uid = (int) $membership->user_id;
            $teamId = (int) $membership->team_id;
            $person = $people->get($uid);
            if ($person === null) {
                continue;
            }

            $owes = in_array($person->role, [User::ROLE_DEVELOPER, User::ROLE_QA], true);
            $first = $membership->created_at ? substr((string) $membership->created_at, 0, 10) : '';
            $last = collect([
                $membership->left_at ? substr((string) $membership->left_at, 0, 10) : null,
                $person->deactivated_at?->toDateString(),
                $person->deleted_at?->toDateString(),
            ])->filter()->min() ?? '9999-12-31';

            foreach ($facts['workingDays'] as $date) {
                if ($date < $first || $date > $last) {
                    continue;
                }

                $covered[$uid][$date] = $owes || ($covered[$uid][$date] ?? false);
                $this->addDay($this->parts($facts, $uid, $teamId), $days[$uid][$teamId][$date] ?? null, $date, $today, $owes);
            }
        }

        // Across teams, a calendar day counts once.
        foreach ($covered as $uid => $dates) {
            $sheet = $this->blankSheet();
            foreach ($dates as $date => $owes) {
                $rows = array_filter(array_map(fn (array $team) => $team[$date] ?? null, $days[$uid] ?? []));
                $merged = $rows === [] ? null : [
                    'filled' => in_array(true, array_column($rows, 'filled'), true),
                    'fullLeave' => in_array(true, array_column($rows, 'fullLeave'), true),
                    'halfDay' => in_array(true, array_column($rows, 'halfDay'), true),
                ];
                $this->addDay($sheet, $merged, $date, $today, $owes);
            }
            $facts['sheet'][$uid] = $sheet;
        }
    }

    /** @param  array<string, mixed>  $parts */
    private function addDay(array &$parts, ?array $row, string $date, string $today, bool $owes): void
    {
        if ($row['fullLeave'] ?? false) {
            $parts['leaveDays']++;

            return;
        }

        $parts['availableDays'] += ($row['halfDay'] ?? false) ? 0.5 : 1;
        $parts['halfDays'] += ($row['halfDay'] ?? false) ? 1 : 0;

        // Today isn't owed until it's filled, so a morning never reads as a miss.
        if ($owes && ($date < $today || ($row['filled'] ?? false))) {
            $parts['owed']++;
            $parts['filled'] += ($row['filled'] ?? false) ? 1 : 0;
        }
    }

    private function addMeetings(array &$facts, Carbon $start, Carbon $end, ?int $userId): void
    {
        $rows = DB::table('event_user')
            ->join('events', 'events.id', '=', 'event_user.event_id')
            ->where('events.type', 'meeting')
            ->where('events.visibility', '!=', Event::VISIBILITY_PRIVATE)
            ->whereBetween('events.starts_at', [$start, $end])
            ->when($userId, fn ($q) => $q->where('event_user.user_id', $userId))
            ->get(['event_user.user_id', 'events.id', 'events.starts_at', 'events.ends_at', 'events.all_day']);

        foreach ($rows as $row) {
            $whole = &$this->whole($facts, (int) $row->user_id);
            $whole['meetings']++;
            // An all-day meeting has no length worth adding up.
            if ($row->ends_at && ! $row->all_day) {
                $whole['meetingMinutes'] += max(0, (int) Carbon::parse($row->starts_at)->diffInMinutes(Carbon::parse($row->ends_at)));
            }
            unset($whole);
        }
    }

    private function addCollaboration(array &$facts, Carbon $from, Carbon $to, Carbon $start, Carbon $end, ?int $userId): void
    {
        $add = function (string $key, iterable $counts) use (&$facts) {
            foreach ($counts as $uid => $count) {
                if ($uid) {
                    $whole = &$this->whole($facts, (int) $uid);
                    $whole[$key] += (int) $count;
                    unset($whole);
                }
            }
        };

        $add('comments', Comment::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('user_id, count(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id'));

        $add('notesWritten', MeetingNote::query()
            ->whereDate('meeting_date', '>=', $from->toDateString())
            ->whereDate('meeting_date', '<=', $to->toDateString())
            ->when($userId, fn ($q) => $q->where('created_by', $userId))
            ->selectRaw('created_by, count(*) as aggregate')
            ->groupBy('created_by')
            ->pluck('aggregate', 'created_by'));

        $add('notesAttended', DB::table('meeting_note_user')
            ->join('meeting_notes', 'meeting_notes.id', '=', 'meeting_note_user.meeting_note_id')
            // A generated standup's attendees are who was expected, not who attended.
            ->whereNotIn('meeting_notes.id', DailyStandup::query()->whereNotNull('meeting_note_id')->select('meeting_note_id'))
            ->whereDate('meeting_notes.meeting_date', '>=', $from->toDateString())
            ->whereDate('meeting_notes.meeting_date', '<=', $to->toDateString())
            ->when($userId, fn ($q) => $q->where('meeting_note_user.user_id', $userId))
            ->selectRaw('meeting_note_user.user_id as user_id, count(*) as aggregate')
            ->groupBy('meeting_note_user.user_id')
            ->pluck('aggregate', 'user_id'));

        $add('aiUses', AiUsageLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'ok')
            ->whereNotNull('user_id')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('user_id, count(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id'));
    }

    private function addRatings(array &$facts, Carbon $from, Carbon $to, ?int $userId): void
    {
        $scores = PerformanceScore::query()
            ->whereDate('period_start', '>=', $from->toDateString())
            ->whereDate('period_start', '<=', $to->toDateString())
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get(['id', 'user_id', 'team_id', 'competency_id', 'score']);

        foreach ($scores as $score) {
            $facts['scores'][$score->user_id][$score->team_id][] = $score;
        }
    }

    /**
     * Each person's latest finished KPI review overlapping the period. Its
     * analysis is large, so only the rows actually shown are read in full.
     */
    private function addKpi(array &$facts, Carbon $from, Carbon $to, ?int $userId): void
    {
        $reports = KpiReport::query()
            ->where('status', KpiReport::STATUS_COMPLETED)
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereDate('period_end', '>=', $from->toDateString())
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->get(['id', 'period_start', 'period_end']);

        if ($reports->isEmpty()) {
            return;
        }

        $order = $reports->pluck('id')->flip();
        $latest = KpiReportMember::query()
            ->whereIn('kpi_report_id', $reports->pluck('id'))
            ->where('status', KpiReportMember::STATUS_COMPLETED)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get(['id', 'kpi_report_id', 'user_id'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->sortBy(fn (KpiReportMember $m) => $order[$m->kpi_report_id])->first());

        $byId = $reports->keyBy('id');
        $analysed = KpiReportMember::query()->whereKey($latest->pluck('id'))->get(['id', 'kpi_report_id', 'user_id', 'analysis']);

        foreach ($analysed as $row) {
            $days = $row->analysis['days'] ?? [];
            $totals = KpiReviewService::totals($days);
            $report = $byId[$row->kpi_report_id];
            $suggested = $row->analysis['overall']['suggested_rating']['score'] ?? null;

            $facts['kpi'][$row->user_id] = [
                'reportId' => $row->kpi_report_id,
                'from' => $report->period_start,
                'to' => $report->period_end,
                'planned' => $totals['kpi'],
                'completed' => $totals['done'],
                'days' => count($days),
                'suggestedRating' => $suggested === null ? null : max(PerformanceScore::MIN_SCORE, min(PerformanceScore::MAX_SCORE, (int) $suggested)),
            ];
        }
    }

    /** Everyone the facts mention, including people deleted since. */
    private function users(array $facts): Collection
    {
        $ids = collect(array_keys($facts['parts']))
            ->merge(array_keys($facts['whole']))
            ->merge(array_keys($facts['scores']))
            ->merge(array_keys($facts['kpi']))
            ->merge($facts['memberships']->pluck('user_id'))
            ->unique();

        return User::withTrashed()->whereKey($ids)->orderBy('name')->get()->keyBy('id');
    }

    // ---- parts --------------------------------------------------------------

    /** @return array<string, mixed> */
    private function &parts(array &$facts, int $userId, int $bucket): array
    {
        $facts['parts'][$userId][$bucket] ??= $this->blank();

        return $facts['parts'][$userId][$bucket];
    }

    /** @return array<string, int> */
    private function &whole(array &$facts, int $userId): array
    {
        $facts['whole'][$userId] ??= $this->blankWhole();

        return $facts['whole'][$userId];
    }

    /** @return array<string, mixed> */
    private function blank(): array
    {
        return [
            'completed' => 0, 'dated' => 0, 'onTime' => 0, 'reworked' => 0, 'cycles' => [], 'leads' => [],
            'open' => 0, 'todo' => 0, 'inProgress' => 0, 'inReview' => 0, 'recheck' => 0, 'overdue' => 0,
            'blockedNow' => 0, 'blockedSeconds' => 0, 'unestimated' => 0,
            'loggedMinutes' => 0, 'comparedTasks' => 0, 'estimatedMinutes' => 0, 'comparedLogged' => 0,
            'late' => 0, 'partial' => 0, 'planned' => 0, 'planWithResult' => 0,
            'workPoints' => 0, 'tickets' => 0, 'ticketPoints' => 0, 'standupMarked' => 0, 'standupAttended' => 0,
            'raised' => 0, 'accepted' => 0, 'declined' => 0, 'pending' => 0, 'decided' => 0,
        ] + $this->blankSheet();
    }

    /** @return array<string, int|float> the parts that differ between one team and every team */
    private function blankSheet(): array
    {
        return ['owed' => 0, 'filled' => 0, 'leaveDays' => 0, 'halfDays' => 0, 'availableDays' => 0.0];
    }

    /** @return array<string, int> */
    private function blankWhole(): array
    {
        return ['meetings' => 0, 'meetingMinutes' => 0, 'notesAttended' => 0, 'notesWritten' => 0, 'comments' => 0, 'aiUses' => 0];
    }

    /**
     * @param  list<array<string, mixed>>  $sets
     * @return array<string, mixed>
     */
    private function merge(array $sets): array
    {
        $total = $this->blank();
        foreach ($sets as $parts) {
            foreach ($parts as $key => $value) {
                $total[$key] = is_array($value) ? [...$total[$key], ...$value] : $total[$key] + $value;
            }
        }

        return $total;
    }

    /**
     * @param  list<array<string, int>>  $sets
     * @return array<string, int>
     */
    private function mergeWhole(array $sets): array
    {
        $total = $this->blankWhole();
        foreach ($sets as $whole) {
            foreach ($whole as $key => $value) {
                $total[$key] += $value;
            }
        }

        return $total;
    }

    private function hasFigures(array $parts): bool
    {
        foreach ($parts as $key => $value) {
            // A span of membership alone isn't work.
            if ($key !== 'owed' && $key !== 'availableDays' && $value !== 0 && $value !== [] && $value !== 0.0) {
                return true;
            }
        }

        return false;
    }

    // ---- rows ---------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function peopleRows(array $facts, ?int $teamId): array
    {
        $rows = [];
        foreach ($facts['users'] as $user) {
            $row = $this->personRow($facts, $user, $teamId);
            $memberships = $facts['membershipsByUser']->get($user->id, collect());
            $member = $teamId ? $memberships->contains(fn ($m) => (int) $m->team_id === $teamId) : $memberships->isNotEmpty();
            $working = $member && $user->deleted_at === null
                && ($user->deactivated_at === null || $user->deactivated_at->gte($facts['from']));

            $hasWork = $this->hasFigures($row['parts'])
                || (! $teamId && $row['whole'] !== $this->blankWhole())
                || ($row['record']['ratings']['count'] > 0);

            if ($working || $hasWork) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function personRow(array $facts, User $user, ?int $teamId): array
    {
        $buckets = $facts['parts'][$user->id] ?? [];

        if ($teamId) {
            $parts = $buckets[$teamId] ?? $this->blank();
            $scores = $facts['scores'][$user->id][$teamId] ?? [];
        } else {
            // Across teams a day counts once, so the summed team days are replaced.
            $parts = array_replace($this->merge(array_values($buckets)), $facts['sheet'][$user->id] ?? $this->blankSheet());
            $scores = array_merge(...array_values($facts['scores'][$user->id] ?? [[]]));
        }

        $whole = $facts['whole'][$user->id] ?? $this->blankWhole();
        $rating = $this->rating($user->role, $scores);
        $kpi = $facts['kpi'][$user->id] ?? null;

        $teamNames = $facts['membershipsByUser']->get($user->id, collect())
            ->filter(fn ($m) => $m->left_at === null)
            ->map(fn ($m) => $facts['teams']->get($m->team_id)?->name)
            ->filter()->sort()->values()->all();

        return [
            'user' => $user,
            'teams' => $teamNames,
            'tag' => $this->tag($facts, $user, $teamId),
            'parts' => $parts,
            'whole' => $whole,
            'kpiParts' => $kpi,
            'categories' => $rating['categories'],
            'record' => $this->record($parts, $whole, $rating, $kpi),
        ];
    }

    /** How a person relates to the selected team, when it isn't plain membership. */
    private function tag(array $facts, User $user, ?int $teamId): ?string
    {
        if (! $teamId) {
            return null;
        }

        $membership = $facts['membershipsByUser']->get($user->id, collect())->first(fn ($m) => (int) $m->team_id === $teamId);

        return match (true) {
            $membership === null => 'Not a member',
            $membership->left_at !== null => 'Left',
            default => null,
        };
    }

    /** @return list<array<string, mixed>> */
    private function teamRows(array $facts): array
    {
        $rows = [];
        foreach ($facts['teams'] as $team) {
            // Who's on the team today: not people who left, were deactivated or were deleted.
            $current = $facts['membershipsByTeam']->get($team->id, collect())->filter(function ($m) use ($facts) {
                $user = $facts['users']->get($m->user_id);

                return $m->left_at === null && $user && ! $user->trashed() && $user->isActive();
            });
            $row = $this->teamRow($facts, $team->id, $team->name, $team->color, $team->teamLead, $current->count(), $team->isArchived());

            if ($team->isArchived() && ! $this->hasFigures($row['parts'])) {
                continue;
            }
            unset($row['parts']);
            $rows[] = $row;
        }

        $none = $this->teamRow($facts, self::NO_TEAM, 'No team', null, null, 0, false);
        if ($this->hasFigures($none['parts'])) {
            unset($none['parts']);
            $rows[] = $none;
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function teamRow(array $facts, int $bucket, string $name, ?string $color, ?User $lead, int $people, bool $archived): array
    {
        $parts = $this->merge(array_values(array_filter(array_map(
            fn (array $buckets) => $buckets[$bucket] ?? null,
            $facts['parts'],
        ))));

        $memberIds = $facts['membershipsByTeam']->get($bucket, collect())->pluck('user_id')->map(fn ($id) => (int) $id)->unique();

        $whole = $this->mergeWhole($memberIds->map(fn (int $id) => $facts['whole'][$id] ?? $this->blankWhole())->values()->all());

        $ratings = [];
        foreach ($facts['scores'] as $uid => $byTeam) {
            if (! empty($byTeam[$bucket]) && ($user = $facts['users']->get($uid))) {
                $ratings[] = ['record' => ['ratings' => $this->ratingFigures($this->rating($user->role, $byTeam[$bucket]))]];
            }
        }

        $kpiRows = $memberIds->map(fn (int $id) => ['kpiParts' => $facts['kpi'][$id] ?? null])->values()->all();

        return [
            'id' => $bucket ?: null,
            'name' => $name,
            'color' => $color,
            'lead' => $lead,
            'people' => $people,
            'archived' => $archived,
            'parts' => $parts,
            'record' => $this->record($parts, $whole, $this->averageRating($ratings), $this->sumKpi($kpiRows)),
        ];
    }

    /** @return list<array<string, mixed>> a person's figures on each team they worked on */
    private function personByTeam(array $facts, User $user): array
    {
        $rows = [];
        foreach ($facts['parts'][$user->id] ?? [] as $bucket => $parts) {
            // A sheet they owe and never filled is worth seeing too.
            if (! $this->hasFigures($parts) && $parts['owed'] === 0) {
                continue;
            }

            $rating = $this->rating($user->role, $facts['scores'][$user->id][$bucket] ?? []);
            $team = $facts['teams']->get($bucket);
            $rows[] = [
                'id' => $team?->id,
                'name' => $team?->name ?? 'No team',
                'color' => $team?->color,
                'record' => $this->record($parts, $facts['whole'][$user->id] ?? $this->blankWhole(), $rating, $facts['kpi'][$user->id] ?? null),
            ];
        }

        return collect($rows)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /** @return array<string, mixed> */
    private function withoutParts(array $row): array
    {
        unset($row['parts'], $row['whole'], $row['kpiParts'], $row['categories']);

        return $row;
    }

    // ---- figures ------------------------------------------------------------

    /**
     * Rates from the summed parts. Grouped so that a weighted scorecard could
     * later read these groups; nothing here combines them.
     *
     * @return array<string, array<string, mixed>>
     */
    private function record(array $parts, array $whole, array $rating, ?array $kpi): array
    {
        $cycles = $parts['cycles'];
        $leads = $parts['leads'];

        return [
            'delivery' => [
                'completed' => $parts['completed'],
                'perDay' => $parts['availableDays'] > 0 ? round($parts['completed'] / $parts['availableDays'], 2) : null,
                'dated' => $parts['dated'],
                'onTimeCount' => $parts['onTime'],
                'onTime' => Metric::measure(Metric::pct($parts['onTime'], $parts['dated']), $parts['dated'], self::MIN_TASKS),
                'cycle' => Metric::measure($this->median($cycles), count($cycles), self::MIN_TASKS),
                'cycleAverage' => $cycles === [] ? null : round(array_sum($cycles) / count($cycles), 1),
                'lead' => Metric::measure($this->median($leads), count($leads), self::MIN_TASKS),
            ],
            'quality' => [
                'reworked' => $parts['reworked'],
                'reworkRate' => Metric::measure(Metric::pct($parts['reworked'], $parts['completed']), $parts['completed'], self::MIN_TASKS),
            ],
            'workload' => [
                'open' => $parts['open'],
                'todo' => $parts['todo'],
                'inProgress' => $parts['inProgress'],
                'inReview' => $parts['inReview'],
                'recheck' => $parts['recheck'],
                'overdue' => $parts['overdue'],
                'blockedNow' => $parts['blockedNow'],
                'blockedDays' => round($parts['blockedSeconds'] / 86400, 1),
                'unestimated' => $parts['unestimated'],
            ],
            'time' => [
                'loggedMinutes' => $parts['loggedMinutes'],
                'availableMinutes' => (int) round($parts['availableDays'] * config('planning.hours_per_day') * 60),
                'comparedTasks' => $parts['comparedTasks'],
                'estimatedMinutes' => $parts['estimatedMinutes'],
                'comparedLogged' => $parts['comparedLogged'],
                'ratio' => Metric::measure(
                    $parts['estimatedMinutes'] > 0 ? (int) round($parts['comparedLogged'] / $parts['estimatedMinutes'] * 100) : null,
                    $parts['comparedTasks'],
                    self::MIN_TASKS,
                ),
            ],
            'tasksheet' => [
                'owed' => $parts['owed'],
                'filled' => $parts['filled'],
                'rate' => Metric::measure(Metric::pct($parts['filled'], $parts['owed']), $parts['owed'], self::MIN_DAYS),
                'late' => $parts['late'],
                'partial' => $parts['partial'],
                'planned' => $parts['planned'],
                'planWithResult' => $parts['planWithResult'],
                'planRate' => Metric::measure(Metric::pct($parts['planWithResult'], $parts['planned']), $parts['planned'], self::MIN_DAYS),
                'leaveDays' => $parts['leaveDays'],
                'halfDays' => $parts['halfDays'],
                'workPoints' => $parts['workPoints'],
                'tickets' => $parts['tickets'],
                'ticketPoints' => $parts['ticketPoints'],
                'standupMarked' => $parts['standupMarked'],
                'standupAttended' => $parts['standupAttended'],
                'standupRate' => Metric::measure(Metric::pct($parts['standupAttended'], $parts['standupMarked']), $parts['standupMarked'], self::MIN_DAYS),
            ],
            'requests' => [
                'raised' => $parts['raised'],
                'accepted' => $parts['accepted'],
                'declined' => $parts['declined'],
                'pending' => $parts['pending'],
                'decided' => $parts['decided'],
            ],
            'collaboration' => $whole,
            'ratings' => $this->ratingFigures($rating),
            'kpi' => $this->kpiFigures($kpi),
        ];
    }

    /** @param  list<PerformanceScore>  $scores */
    private function rating(string $role, array $scores): array
    {
        return $this->performance->overallFor($role, collect($scores)) + ['count' => count($scores)];
    }

    /** @return array<string, mixed> */
    private function ratingFigures(array $rating): array
    {
        return [
            // Half the competencies that apply, or the score says little about the person.
            'overall' => array_key_exists('peopleRated', $rating)
                ? Metric::measure($rating['overall'], $rating['peopleRated'], 1)
                : Metric::measure($rating['overall'], $rating['rated'], max(1, (int) ceil($rating['applicable'] / 2))),
            'rated' => $rating['rated'],
            'applicable' => $rating['applicable'],
            'count' => $rating['count'],
        ];
    }

    /**
     * A group's rating: the average of its members who were rated enough to
     * quote, as on the Performance page.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function averageRating(array $rows): array
    {
        $rated = collect($rows)->map(fn (array $row) => $row['record']['ratings'])->values();
        $quoted = $rated->filter(fn (array $r) => $r['overall']['enough']);

        return [
            'overall' => $quoted->isEmpty() ? null : round($quoted->avg(fn (array $r) => $r['overall']['value']), 2),
            'peopleRated' => $quoted->count(),
            // Competency counts belong to one person, so a group has none.
            'rated' => null,
            'applicable' => null,
            'count' => (int) $rated->sum('count'),
        ];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function sumKpi(array $rows): ?array
    {
        $kpis = collect($rows)->pluck('kpiParts')->filter();
        if ($kpis->isEmpty()) {
            return null;
        }

        return [
            'reportId' => null,
            'from' => null,
            'to' => null,
            'planned' => (int) $kpis->sum('planned'),
            'completed' => (int) $kpis->sum('completed'),
            'days' => (int) $kpis->sum('days'),
            'suggestedRating' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function kpiFigures(?array $kpi): array
    {
        $percent = $kpi && $kpi['planned'] > 0 ? round($kpi['completed'] / $kpi['planned'] * 100, 1) : null;

        return [
            'reportId' => $kpi['reportId'] ?? null,
            'from' => $kpi['from'] ?? null,
            'to' => $kpi['to'] ?? null,
            'planned' => $kpi['planned'] ?? null,
            'completed' => $kpi['completed'] ?? null,
            'percent' => Metric::measure($percent, $kpi['days'] ?? 0, self::MIN_DAYS),
            'status' => KpiReviewService::status($percent) ?: null,
            'suggestedRating' => $kpi['suggestedRating'] ?? null,
        ];
    }

    /** @param  list<float>  $values */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return round(count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2, 1);
    }

    /**
     * The headline figures beside the period before. A figure with too little
     * data on either side has no comparison.
     *
     * @return array<string, array{current: int|float|null, previous: int|float|null, change: int|float|null}>
     */
    private function compare(array $now, array $before): array
    {
        $quoted = fn (array $measure) => $measure['enough'] ? $measure['value'] : null;

        return [
            'completed' => Metric::compare($now['delivery']['completed'], $before['delivery']['completed']),
            'onTime' => Metric::compare($quoted($now['delivery']['onTime']), $quoted($before['delivery']['onTime'])),
            'cycle' => Metric::compare($quoted($now['delivery']['cycle']), $quoted($before['delivery']['cycle'])),
            'logged' => Metric::compare($now['time']['loggedMinutes'], $before['time']['loggedMinutes']),
            'tasksheet' => Metric::compare($quoted($now['tasksheet']['rate']), $quoted($before['tasksheet']['rate'])),
            'rating' => Metric::compare($quoted($now['ratings']['overall']), $quoted($before['ratings']['overall'])),
        ];
    }

    // ---- trend --------------------------------------------------------------

    /**
     * Tasks finished and hours logged per week, or per month over a long period.
     *
     * @return array{unit: string, buckets: list<array{label: string, start: Carbon, end: Carbon, completed: int, minutes: int}>}
     */
    private function trend(array $facts, Carbon $from, Carbon $to, ?int $teamId, ?int $userId = null): array
    {
        $weekly = $from->diffInDays($to) < self::WEEKLY_MAX_DAYS;
        $key = fn (string $date) => $weekly
            ? Carbon::parse($date)->startOfWeek()->toDateString()
            : substr($date, 0, 7).'-01';

        $buckets = [];
        $cursor = Carbon::parse($key($from->toDateString()));
        while ($cursor->lte($to)) {
            $end = $weekly ? $cursor->copy()->endOfWeek() : $cursor->copy()->endOfMonth();
            $buckets[$cursor->toDateString()] = [
                'label' => $weekly ? $cursor->format('M j') : $cursor->format('M Y'),
                'start' => $cursor->copy()->max($from),
                'end' => $end->copy()->min($to->copy()->endOfDay()),
                'completed' => 0,
                'minutes' => 0,
            ];
            $cursor = $weekly ? $cursor->copy()->addWeek() : $cursor->copy()->addMonthNoOverflow();
        }

        foreach ($facts['completions'] as $c) {
            if ((! $teamId || $c['bucket'] === $teamId) && (! $userId || $c['assigneeId'] === $userId)) {
                $k = $key($c['completedAt']->toDateString());
                if (isset($buckets[$k])) {
                    $buckets[$k]['completed']++;
                }
            }
        }

        foreach ($facts['hoursByDay'] as $row) {
            if ((! $teamId || $row['bucket'] === $teamId) && (! $userId || $row['userId'] === $userId)) {
                $k = $key($row['date']);
                if (isset($buckets[$k])) {
                    $buckets[$k]['minutes'] += $row['minutes'];
                }
            }
        }

        return ['unit' => $weekly ? 'week' : 'month', 'buckets' => array_values($buckets)];
    }

    // ---- task lists ---------------------------------------------------------

    /**
     * The tasks behind a person's finished count, newest first.
     *
     * @return list<array{task: Task, completedAt: Carbon, cycleDays: ?float, onTime: ?bool, reworked: bool}>
     */
    private function finishedTasks(array $facts, ?int $teamId): array
    {
        $done = $facts['completions']
            ->filter(fn (array $c) => ! $teamId || $c['bucket'] === $teamId)
            ->sortByDesc(fn (array $c) => $c['completedAt']->getTimestamp())
            ->values();

        $tasks = Task::query()
            ->whereKey($done->pluck('taskId'))
            ->with(['release:id,name', 'team:id,name'])
            ->withSum('timeLogs as logged_minutes', 'minutes')
            ->get()
            ->keyBy('id');

        return $done->filter(fn (array $c) => $tasks->has($c['taskId']))
            ->map(fn (array $c) => [
                'task' => $tasks[$c['taskId']],
                'completedAt' => $c['completedAt'],
                'cycleDays' => ! $c['estimated'] && $c['startedAt'] ? round($c['startedAt']->diffInSeconds($c['completedAt']) / 86400, 1) : null,
                'onTime' => $c['dueDate'] ? $c['completedAt']->toDateString() <= $c['dueDate']->toDateString() : null,
                'reworked' => $c['reworked'],
            ])
            ->values()
            ->all();
    }

    /**
     * A person's open tasks now: overdue first, then by due date.
     *
     * @return list<Task>
     */
    private function openTasks(array $facts, User $user, ?int $teamId): array
    {
        return Task::query()
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->with(['release:id,name', 'team:id,name'])
            ->withExists('openBlockers as has_open_blockers')
            ->withSum('timeLogs as logged_minutes', 'minutes')
            ->get()
            ->filter(fn (Task $t) => ! $teamId || $this->bucket($facts, $t->team_id, $user->id) === $teamId)
            ->sortBy(fn (Task $t) => ($t->due_date?->toDateString() ?? '9999-12-31').'#'.$t->id)
            ->values()
            ->all();
    }
}

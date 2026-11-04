<?php

namespace App\Services\Ai;

use App\Models\Release;
use App\Models\ReleaseStatusUpdate;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\TaskStatusChange;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\TriageItem;
use App\Models\User;
use App\Services\Board\PlanToBoardService;
use App\Services\CapacityService;
use App\Services\FlowReportService;
use App\Services\NotificationService;
use App\Services\TasksheetService;
use App\Services\TimeReportService;
use App\Services\TriageService;
use App\Support\Duration;
use App\Support\WorkingWeek;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The planning figures "Ask your workspace" can answer from, as records. Each
 * digest makes the same check its own page makes (see the table in the
 * ask-workspace-planning-data design), so the AI never holds a number the asker
 * couldn't open. Performance scores and tasksheet feedback and points aren't
 * here on purpose: they're restricted, and the columns are never even loaded.
 */
class WorkspaceDigests
{
    /** Teams a digest covers when the question names none and the asker belongs to none. */
    private const DEFAULT_TEAMS = 5;

    private const FLOW_TEAMS = 2;

    /** Weeks of capacity shown per person, out of the page's six. */
    private const CAPACITY_WEEKS = 3;

    private const LISTED_CHANGES = 30;

    private const LISTED_NEW_TASKS = 10;

    private const LISTED_STATUS_UPDATES = 10;

    private const LISTED_QUEUE = 15;

    private const LISTED_OWN_REQUESTS = 10;

    private const LISTED_NOTIFICATIONS = 15;

    private const LISTED_TASKSHEET_ROWS = 30;

    private const CHANGES_DAYS = 7;

    private const TIME_REPORT_DAYS = 30;

    private const LEAVE_AHEAD_DAYS = 14;

    public function __construct(
        private readonly CapacityService $capacity,
        private readonly FlowReportService $flow,
        private readonly TimeReportService $time,
        private readonly TasksheetService $tasksheet,
        private readonly TriageService $triage,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Digests the question calls for, and the tasks and releases they cite so
     * those references resolve to links.
     *
     * @param  Collection<int, Team>  $teams  teams the question names, already within what the asker may see
     * @param  Collection<int, Release>  $releases  releases the question names, already within what the asker may see
     * @param  Collection<int, User>  $people  people the question names, already within what the asker may see
     * @return array{records: Collection<int, array>, task_ids: list<int>, release_ids: list<int>}
     */
    public function for(AskQuestion $question, User $user, Collection $teams, Collection $releases, Collection $people): array
    {
        $taskIds = collect();
        $releaseIds = collect();
        $records = collect();

        $add = function (array $digest) use (&$records, &$taskIds, &$releaseIds) {
            $records = $records->concat($digest['records'] ?? []);
            $taskIds = $taskIds->concat($digest['task_ids'] ?? []);
            $releaseIds = $releaseIds->concat($digest['release_ids'] ?? []);
        };

        if ($question->asks('capacity')) {
            $add($this->capacityFor($user, $teams));
        }
        if ($question->asks('flow')) {
            $add($this->flowFor($user, $teams));
        }
        if ($question->asks('time')) {
            $add($this->timeFor($question, $user, $teams, $releases));
        }
        if ($question->asks('tasksheet', 'capacity')) {
            $add($this->tasksheetFor($question, $user, $teams, $people));
        }
        if ($question->asks('notifications', 'changes')) {
            $add($this->notificationsFor($user));
        }
        if ($question->asks('changes')) {
            $add($this->changesFor($question, $user, $teams, $releases));
        }
        $add($this->requestsFor($question, $user));

        return [
            'records' => $records->values(),
            'task_ids' => $taskIds->unique()->values()->all(),
            'release_ids' => $releaseIds->unique()->values()->all(),
        ];
    }

    /** Who has room: leads only, and team leads only for the teams they lead (CapacityService::teamsFor). */
    private function capacityFor(User $user, Collection $named): array
    {
        if (! $user->isLead()) {
            return [];
        }

        $allowed = $this->capacity->teamsFor($user);
        $teams = $this->pick($allowed, $named, $user);
        $others = $allowed->whereNotIn('id', $teams->pluck('id'))->pluck('name');

        return ['records' => $teams->values()->map(function (Team $team, int $i) use ($others) {
            $plan = $this->capacity->forTeam($team);
            $weeks = array_slice($plan['weeks'], 0, self::CAPACITY_WEEKS);

            $rows = collect($plan['people'])->map(function (array $row) use ($weeks) {
                $cells = collect($weeks)->map(function (array $week, int $w) use ($row) {
                    $cell = $row['cells'][$w];

                    return sprintf('week of %s%s: %s free of %s available, %d tasks%s%s',
                        $week['start']->toDateString(), $week['current'] ? ' (this week, days left only)' : '',
                        Duration::format(max(0, $cell['available_minutes'] - $cell['load_minutes'])),
                        Duration::format($cell['available_minutes']), $cell['tasks'],
                        $cell['unestimated'] ? ", {$cell['unestimated']} with no estimate" : '',
                        $cell['over'] ? ', OVER capacity' : '');
                })->implode('; ');

                return "{$row['user']->name}: {$cells}; undated or later: {$row['later']['tasks']} tasks.";
            })->implode("\n");

            return [
                'ref' => "C{$team->id}", 'kind' => 'Capacity', 'title' => "Capacity of {$team->name}", 'url' => route('capacity.index', ['team_id' => $team->id]),
                'text' => "Capacity of team {$team->name}. Available time already leaves out weekends, release off-days and recorded leave. Work is what's left of estimates; tasks with no estimate add no load, so someone can look free when their work isn't estimated.\n"
                    .($rows ?: 'No active members.')
                    .($i === 0 && $others->isNotEmpty() ? "\nOther teams the asker can ask about by name: ".$others->implode(', ').'.' : ''),
            ];
        })];
    }

    /** How work moves: leads and viewers, as on the Flow page. Team-level, so it never names who did what. */
    private function flowFor(User $user, Collection $named): array
    {
        if ($user->hasLimitedAccess()) {
            return [];
        }

        $teams = ($named->isNotEmpty() ? $named : $this->homeTeams($user))->take(self::FLOW_TEAMS);
        $scopes = $teams->isEmpty() ? collect([null]) : $teams->values();
        $taskIds = collect();

        $records = $scopes->map(function (?Team $team) use (&$taskIds) {
            $report = $this->flow->report($team?->id);
            $taskIds = $taskIds->concat(array_column($report['aging'], 'task_id'));

            $weeks = collect($report['weeks'])->keys()->slice(-6)->map(fn (int $i) => sprintf('week of %s: %d came in, %d finished',
                $report['weeks'][$i]['start']->toDateString(), $report['arrivals'][$i], $report['completions'][$i],
            ))->implode('; ');
            $now = collect(end($report['flow']))->map(fn (int $n, string $status) => (Task::STATUSES[$status] ?? $status).' '.$n)->implode(', ');
            $aging = collect($report['aging'])->map(fn (array $a) => "[T{$a['task_id']}] {$a['key']} {$a['title']} ({$a['status']}, {$a['days']} days)")->implode('; ');
            $name = $team ? "team {$team->name}" : 'all teams';

            return [
                'ref' => 'F'.($team?->id ?? 0), 'kind' => 'Flow report', 'title' => "Flow of {$name}", 'url' => route('reports.flow', array_filter(['team_id' => $team?->id])),
                'text' => sprintf('Flow of %s, top-level tasks over %d weeks: %d finished, %d in progress now. Start to finish: half within %s days, 85%% within %s days. %s. Status counts now: %s.%s',
                    $name, FlowReportService::WEEKS, $report['finished'], $report['in_progress'],
                    $report['cycle_p50'] ?? 'unknown', $report['cycle_p85'] ?? 'unknown', ucfirst($weeks), $now,
                    $aging ? " Running longer than 85% of finished work: {$aging}." : ''),
            ];
        });

        return ['records' => $records, 'task_ids' => $taskIds->all()];
    }

    /** Where the hours went: leads within the report's own reach, plus everyone's own hours but a viewer's. */
    private function timeFor(AskQuestion $question, User $user, Collection $named, Collection $releases): array
    {
        $period = $question->periodOr(self::TIME_REPORT_DAYS);
        $out = ['records' => [], 'task_ids' => []];

        if (! $user->isViewer()) {
            $own = $this->ownTime($user, $period);
            $out['records'][] = $own['record'];
            $out['task_ids'] = $own['task_ids'];
        }

        // Filtering a team lead by a team they don't lead would read as "no hours".
        $team = $named->first();
        if (! $user->isLead() || ($team && ! $user->canAccessTeamPerformance($team))) {
            return $out;
        }

        $filters = $period + ['team_id' => $team?->id, 'release_id' => $releases->count() === 1 ? $releases->first()->id : null];
        $report = $this->time->report($filters, $user);
        $sums = fn (array $rows) => collect($rows)->take(8)->map(fn (array $r) => $r['name'].' '.Duration::format($r['minutes']))->implode(', ') ?: 'none';
        $accuracy = $report['accuracy'];
        $misses = collect($accuracy['misses'])->map(fn (array $m) => sprintf('[T%d] %s %s: estimated %s, logged %s',
            $m['task_id'], $m['key'], $m['title'], Duration::format($m['estimated_minutes']), Duration::format($m['logged_minutes'])))->implode('; ');

        $out['records'][] = [
            'ref' => 'L1', 'kind' => 'Time report', 'title' => 'Logged hours',
            'url' => route('reports.time', array_filter(['from' => $filters['from']->toDateString(), 'to' => $filters['to']->toDateString(), 'team_id' => $filters['team_id'], 'release_id' => $filters['release_id']])),
            'text' => sprintf('Logged hours %s to %s%s%s, for planning only: %s in total. By team: %s. By release: %s. By person: %s. Estimates on %d tasks finished in the period: %s logged against %s estimated%s.%s',
                $filters['from']->toDateString(), $filters['to']->toDateString(),
                $team ? ", team {$team->name}" : '', $filters['release_id'] ? ', release '.$releases->first()->name : '',
                Duration::format($report['total_minutes']), $sums($report['by_team']), $sums($report['by_release']), $sums($report['by_person']),
                $accuracy['tasks'], Duration::format($accuracy['logged_minutes']), Duration::format($accuracy['estimated_minutes']),
                $accuracy['ratio'] !== null ? " (ratio {$accuracy['ratio']})" : '',
                $misses ? " Furthest from estimate: {$misses}." : ''),
        ];
        $out['task_ids'] = array_merge($out['task_ids'], array_column($accuracy['misses'], 'task_id'));

        return $out;
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $period
     * @return array{record: array, task_ids: list<int>}
     */
    private function ownTime(User $user, array $period): array
    {
        $logs = TaskTimeLog::query()
            ->where('user_id', $user->id)
            ->whereHas('task', fn ($q) => $q->visibleTo($user))
            ->whereDate('logged_on', '>=', $period['from']->toDateString())
            ->whereDate('logged_on', '<=', $period['to']->toDateString())
            ->selectRaw('task_id, sum(minutes) as minutes')
            ->groupBy('task_id')
            ->orderByDesc('minutes')
            ->get();

        $top = $logs->take(8);
        $tasks = $top->map(fn ($log) => "[T{$log->task_id}] ".Duration::format((int) $log->minutes))->implode(', ');

        return [
            'record' => [
                'ref' => 'Y1', 'kind' => 'Your hours', 'title' => 'Your logged hours', 'url' => route('tasks.index', ['assignee_id' => $user->id]),
                'text' => sprintf('The asker logged %s from %s to %s.%s',
                    Duration::format((int) $logs->sum('minutes')), $period['from']->toDateString(), $period['to']->toDateString(), $tasks ? " Most on: {$tasks}." : ''),
            ],
            'task_ids' => $top->pluck('task_id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    /**
     * Plans, results, leave and standup, for teams the viewer may open. Named
     * columns only, so feedback and points can't reach the AI by a later slip.
     */
    private function tasksheetFor(AskQuestion $question, User $user, Collection $named, Collection $people): array
    {
        $allowed = $this->tasksheet->teamsFor($user);
        $teams = $this->pick($allowed, $named, $user);
        if ($teams->isEmpty()) {
            return [];
        }

        $columns = ['id', 'team_id', 'user_id', 'date', 'plan', 'result', 'leave_type', 'standup_attended'];
        $base = fn () => TasksheetEntry::query()
            ->with(['member:id,name', 'team:id,name'])
            ->whereIn('team_id', $teams->pluck('id'))
            ->when($people->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $people->pluck('id')));

        // Without a period: the last two working days, or a fortnight for one person.
        $period = $question->period ?? ['from' => $people->isNotEmpty() ? today()->subDays(self::LEAVE_AHEAD_DAYS) : WorkingWeek::previous(), 'to' => today()];

        // A capacity question only needs who's away, not what everyone planned.
        $rows = ($question->asks('tasksheet') ? $base()
            ->whereDate('date', '>=', $period['from']->toDateString())
            ->whereDate('date', '<=', $period['to']->toDateString())
            ->latest('date')->limit(self::LISTED_TASKSHEET_ROWS)->get($columns) : collect())
            ->concat($base()
                ->whereNotNull('leave_type')
                ->whereDate('date', '>=', today()->toDateString())
                ->whereDate('date', '<=', today()->addDays(self::LEAVE_AHEAD_DAYS)->toDateString())
                ->orderBy('date')->limit(self::LISTED_TASKSHEET_ROWS)->get($columns))
            ->unique('id');

        return ['records' => $rows->map(function (TasksheetEntry $row) {
            $work = collect(['plan' => $row->plan, 'result' => $row->result])
                ->filter()->map(fn (string $html, string $field) => $field.': '.$this->excerpt($html, 250))->implode('; ');
            $standup = $row->attendedStandup() ? 'attended standup' : ($row->missedStandup() ? 'missed standup' : null);
            $parts = array_filter([$row->isOnLeave() ? $row->leaveLabel() : null, $work ?: null, $standup]);

            return [
                'ref' => "S{$row->id}", 'kind' => 'Tasksheet', 'title' => ($row->member?->name ?? 'Someone').', '.$row->date->toDateString(),
                'url' => route('tasksheet.index', ['team' => $row->team_id, 'date' => $row->date->toDateString()]),
                'text' => ($row->member?->name ?? 'Someone').' on '.$row->date->toDateString().' (team '.($row->team?->name ?? 'none').'): '.(implode('; ', $parts) ?: 'nothing filled in').'.',
            ];
        })->values()];
    }

    /** The asker's own inbox, and nobody else's. */
    private function notificationsFor(User $user): array
    {
        return ['records' => $this->notifications->latest($user, self::LISTED_NOTIFICATIONS)->values()->map(fn ($row, int $i) => [
            'ref' => 'B'.($i + 1), 'kind' => 'Notification', 'title' => Str::limit((string) ($row->data['message'] ?? 'Notification'), 80),
            'url' => route('notifications.index'),
            'text' => sprintf('The asker was notified on %s (%s): %s', $row->created_at->toDateString(), $row->read_at ? 'read' : 'unread', (string) ($row->data['message'] ?? 'a notification')),
        ])];
    }

    /** What moved in a period, across the tasks and releases the asker can see. */
    private function changesFor(AskQuestion $question, User $user, Collection $teams, Collection $releases): array
    {
        $period = $question->periodOr(self::CHANGES_DAYS);
        $from = $period['from']->copy()->startOfDay();
        $to = $period['to']->copy()->endOfDay();
        $focus = fn ($q) => $teams->isEmpty() && $releases->isEmpty()
            ? $q
            : $q->where(fn ($w) => $w->whereIn('team_id', $teams->pluck('id'))->orWhereIn('release_id', $releases->pluck('id')));

        $moves = fn () => TaskStatusChange::query()
            ->whereNotNull('from_status')
            ->whereBetween('changed_at', [$from, $to])
            ->whereHas('task', fn ($q) => $focus($q->visibleTo($user)));
        $listed = $moves()->with('task:id,title')->latest('changed_at')->latest('id')->limit(self::LISTED_CHANGES)->get();

        $created = fn () => $focus(Task::visibleTo($user))->whereBetween('created_at', [$from, $to]);
        $newTasks = $created()->latest()->limit(self::LISTED_NEW_TASKS)->get(['id', 'title']);

        $finished = Release::visibleTo($user)->whereBetween('completed_at', [$from, $to])
            ->when($teams->isNotEmpty(), fn ($q) => $q->whereIn('team_id', $teams->pluck('id')))->get(['id', 'name', 'completed_at']);

        $label = fn (?string $status) => Task::STATUSES[$status] ?? (string) $status;
        $lines = $listed->map(fn (TaskStatusChange $c) => sprintf('%s [T%d] %s: %s to %s',
            $c->changed_at->toDateString(), $c->task_id, $c->task?->reference(), $label($c->from_status), $label($c->to_status)));

        $updates = ReleaseStatusUpdate::query()
            ->whereIn('release_id', Release::visibleTo($user)
                ->when($teams->isNotEmpty() || $releases->isNotEmpty(), fn ($q) => $q->where(fn ($w) => $w
                    ->whereIn('team_id', $teams->pluck('id'))->orWhereIn('id', $releases->pluck('id'))))
                ->select('id'))
            ->whereBetween('created_at', [$from, $to])
            ->with(['release:id,name', 'author:id,name'])
            ->latest()->latest('id')->limit(self::LISTED_STATUS_UPDATES)->get();

        $summary = [
            'ref' => 'H1', 'kind' => 'Changes', 'title' => 'Recent changes', 'url' => route('tasks.index'),
            'text' => sprintf("Changes from %s to %s: %d status moves (%d into Done), %d new tasks, %d releases completed.\n%s%s%s",
                $period['from']->toDateString(), $period['to']->toDateString(), $moves()->count(), $moves()->where('to_status', 'done')->count(), $created()->count(), $finished->count(),
                $finished->map(fn (Release $r) => "Completed [R{$r->id}] {$r->name} on {$r->completed_at->toDateString()}.\n")->implode(''),
                $newTasks->isNotEmpty() ? 'Newest tasks: '.$newTasks->map(fn (Task $t) => "[T{$t->id}]")->implode(', ').".\n" : '',
                $lines->isNotEmpty() ? 'Status moves, newest first'.($moves()->count() > $lines->count() ? ' (showing '.$lines->count().')' : '').":\n".$lines->implode("\n") : 'No status moves.'),
        ];

        return [
            'records' => collect([$summary])->concat($updates->map(fn (ReleaseStatusUpdate $u) => [
                'ref' => "U{$u->id}", 'kind' => 'Status update', 'title' => ($u->release?->name ?? 'Release').': '.$u->statusLabel(), 'url' => route('releases.show', $u->release_id),
                'text' => sprintf('%s marked release %s %s on %s: %s', $u->author?->name ?? 'Someone', $u->release?->name, $u->statusLabel(), $u->created_at->toDateString(), $this->excerpt($u->note, 400)),
            ])),
            'task_ids' => $listed->pluck('task_id')->concat($newTasks->pluck('id'))->all(),
            'release_ids' => $finished->pluck('id')->all(),
        ];
    }

    /**
     * Requests the asker sent, and pending ones they may decide, as on the
     * Requests page. Always matched by keyword; listed in full when asked about.
     */
    private function requestsFor(AskQuestion $question, User $user): array
    {
        $queue = $this->triage->queue($user);
        $mine = $this->triage->mine($user);

        $listed = $question->asks('requests')
            ? $queue->take(self::LISTED_QUEUE)->concat($mine->take(self::LISTED_OWN_REQUESTS))
            : $queue->concat($mine)->filter(fn (TriageItem $item) => $question->words->isNotEmpty()
                && Str::contains(mb_strtolower($item->title.' '.$item->description), $question->words->all()));

        return ['records' => $listed->unique('id')->values()->map(function (TriageItem $item) {
            $outcome = match ($item->status) {
                TriageItem::ACCEPTED => 'accepted'.($item->task_id ? ' as '.Task::KEY_PREFIX.'-'.$item->task_id : ''),
                TriageItem::DECLINED => 'declined'.($item->decline_reason ? ": {$item->decline_reason}" : ''),
                default => 'pending for '.(int) $item->created_at->diffInDays(now()).' days'.($item->isWaitingTooLong() ? ', past the response target' : ''),
            };

            return [
                'ref' => "I{$item->id}", 'kind' => 'Request', 'title' => $item->title, 'url' => route('triage.index'),
                'text' => sprintf('%s; %s; reported by %s on %s; team %s; priority %s%s.%s',
                    $item->title, $outcome, $item->reporter?->name ?? 'someone', $item->created_at->toDateString(), $item->team?->name ?? 'unassigned',
                    Task::PRIORITIES[$item->priority] ?? 'none', $item->label ? "; label {$item->label->name}" : '',
                    filled($item->description) ? ' '.$this->excerpt($item->description, 250) : ''),
            ];
        })];
    }

    /**
     * Teams a digest covers: those named and allowed, else the asker's own and
     * allowed, else the first few allowed.
     *
     * @param  Collection<int, Team>  $allowed
     */
    private function pick(Collection $allowed, Collection $named, User $user): Collection
    {
        if ($named->isNotEmpty()) {
            return $allowed->whereIn('id', $named->pluck('id'))->values();
        }

        $home = $allowed->whereIn('id', $this->homeTeams($user)->pluck('id'))->values();

        return $home->isNotEmpty() ? $home : $allowed->take(self::DEFAULT_TEAMS)->values();
    }

    /** Teams the asker leads or belongs to. */
    private function homeTeams(User $user): Collection
    {
        return Team::query()
            ->where(fn ($q) => $q->where('team_lead_id', $user->id)->orWhereIn('id', $user->currentTeamIds()))
            ->orderBy('name')
            ->get();
    }

    private function excerpt(?string $html, int $length): string
    {
        return mb_strimwidth((string) preg_replace('/\s+/', ' ', PlanToBoardService::plainText((string) $html)), 0, $length, '…');
    }
}

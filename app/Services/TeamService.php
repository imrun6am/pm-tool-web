<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Teams and their membership.
 *
 * Membership is never erased. Removing someone stamps `left_at` on the pivot so
 * the team's past tasksheets and performance records still know they were
 * there — deleting the row would silently rewrite history. Re-adding them
 * clears the stamp rather than creating a second membership.
 */
class TeamService
{
    /** Sort orders the team directory offers, keyed by their `?sort=` value. */
    public const SORTS = [
        'name' => 'Name',
        'members' => 'Most members',
        'releases' => 'Most active releases',
        'tasks' => 'Most open tasks',
    ];

    /** How many working days the standup-attendance strip covers. */
    public const STANDUP_DAYS = 7;

    public function __construct(
        private readonly TasksheetService $tasksheets,
        private readonly TaskCompletions $completions,
    ) {}

    /**
     * @param  array{status?: ?string, search?: ?string}  $filters
     * @return Builder<Team>
     */
    public function filtered(array $filters = []): Builder
    {
        return Team::query()
            ->with('teamLead')
            ->withCount(['releases', 'members'])
            ->when(($filters['status'] ?? null) === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderByRaw('archived_at is not null') // active first
            ->orderBy('name');
    }

    /**
     * Normalize the team directory's query string. All state lives in the URL:
     * `search`, `archived=1` (include archived teams) and `sort`.
     *
     * @param  array<string, mixed>  $input
     * @return array{search: ?string, archived: bool, sort: string}
     */
    public static function directoryFilters(array $input): array
    {
        $search = is_string($input['search'] ?? null) ? trim($input['search']) : '';
        $sort = is_string($input['sort'] ?? null) ? $input['sort'] : '';

        return [
            'search' => $search !== '' ? $search : null,
            'archived' => filter_var($input['archived'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'name',
        ];
    }

    /**
     * The web directory: filtered() plus what each team card shows — current
     * members (for the avatar stack), active releases and open counted tasks.
     * Archived teams are hidden unless asked for, and always sort last.
     *
     * @param  array{search: ?string, archived: bool, sort: string}  $filters
     * @return Builder<Team>
     */
    public function directory(array $filters): Builder
    {
        $sortColumn = match ($filters['sort']) {
            'members' => 'members_count',
            'releases' => 'active_releases_count',
            'tasks' => 'open_tasks_count',
            default => null,
        };

        return $this->filtered([
            'status' => $filters['archived'] ? null : 'active',
            'search' => $filters['search'],
        ])
            ->with(['members' => fn ($q) => $q->orderBy('name')])
            ->withCount(['releases as active_releases_count' => fn ($q) => $q->ongoing()])
            ->addSelect(['open_tasks_count' => $this->openTasks()
                ->selectRaw('count(*)')
                ->whereColumn('tasks.team_id', 'teams.id')])
            ->reorder()
            ->orderByRaw('archived_at is not null')
            ->when($sortColumn, fn ($q, $column) => $q->orderByDesc($column))
            ->orderBy('name');
    }

    /**
     * Workspace-wide figures for the directory's KPI strip — independent of
     * the filters, so narrowing the list never changes the totals.
     *
     * @return array{teams: int, archived: int, people: int, activeReleases: int, openTasks: int}
     */
    public function overview(): array
    {
        $activeTeamIds = Team::active()->select('id');

        return [
            'teams' => Team::active()->count(),
            'archived' => Team::whereNotNull('archived_at')->count(),
            'people' => User::whereIn('id', DB::table('team_user')
                ->whereNull('left_at')
                ->whereIn('team_id', $activeTeamIds)
                ->select('user_id'))->count(),
            'activeReleases' => Release::ongoing()->whereIn('team_id', $activeTeamIds)->count(),
            'openTasks' => $this->openTasks()->whereIn('team_id', $activeTeamIds)->count(),
        ];
    }

    /**
     * The team's releases in schedule order, with project/team for the
     * timeline and task progress for the compact list.
     *
     * @return Collection<int, Release>
     */
    public function releasesFor(Team $team): Collection
    {
        return $team->releases()
            ->with(['project', 'team'])
            ->withTaskProgress()
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * The team's work in numbers: open and overdue counted tasks, open tasks
     * per assignee (workload), unassigned work, and tasks completed this
     * month (read from the audit trail by TaskCompletions).
     *
     * @return array{open: int, overdue: int, unassigned: int, doneThisMonth: int, byAssignee: Collection<int, int>}
     */
    public function taskSummary(Team $team): array
    {
        $open = fn (): Builder => $this->openTasks()->where('team_id', $team->id);

        return [
            'open' => $open()->count(),
            'overdue' => $open()->whereNotNull('due_date')->whereDate('due_date', '<', today()->toDateString())->count(),
            'unassigned' => $open()->whereNull('assignee_id')->count(),
            'doneThisMonth' => $this->completions
                ->since(fn () => Task::query()->where('team_id', $team->id), now()->startOfMonth())
                ->count(),
            'byAssignee' => $open()
                ->whereNotNull('assignee_id')
                ->groupBy('assignee_id')
                ->selectRaw('assignee_id, count(*) as open_count')
                ->pluck('open_count', 'assignee_id')
                ->map(fn ($count) => (int) $count),
        ];
    }

    /**
     * The latest history for the team: its releases (and everything logged
     * against them), tasks filed to the team without a release, and the team
     * record itself. Activity is a planning surface — callers gate it.
     *
     * @return Collection<int, Activity>
     */
    public function recentActivity(Team $team, int $limit = 12): Collection
    {
        return Activity::query()
            ->where(fn ($q) => $q
                ->whereIn('release_id', Release::select('id')->where('team_id', $team->id))
                ->orWhere(fn ($t) => $t
                    ->where('subject_type', (new Task)->getMorphClass())
                    ->whereIn('subject_id', Task::select('id')->where('team_id', $team->id)))
                ->orWhere(fn ($t) => $t
                    ->where('subject_type', $team->getMorphClass())
                    ->where('subject_id', $team->getKey())))
            ->with('causer')
            ->latest()
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * People who have left the team, most recent first (pivot `left_at`).
     *
     * @return Collection<int, User>
     */
    public function formerMembers(Team $team): Collection
    {
        return $team->memberRecords()
            ->wherePivotNotNull('left_at')
            ->orderByPivot('left_at', 'desc')
            ->get();
    }

    /**
     * Today's tasksheet fill: of the people who belong on today's sheet
     * (TasksheetService::rowUsersFor), who has logged something — task content
     * or a leave. A row created only by a standup mark does not count.
     *
     * @return array{roster: int, logged: int, onLeave: int, missing: Collection<int, User>}
     */
    public function tasksheetFill(Team $team, ?Carbon $day = null): array
    {
        $day ??= today();
        $entries = $this->tasksheets->entriesFor($team, $day);
        $roster = $this->tasksheets->rowUsersFor($team, $day, $entries);

        $hasLogged = function (User $user) use ($entries): bool {
            $entry = $entries->get($user->id);

            return $entry !== null && ($entry->isOnLeave() || $entry->filledFieldCount() > 0);
        };

        return [
            'roster' => $roster->count(),
            'logged' => $roster->filter($hasLogged)->count(),
            'onLeave' => $roster->filter(fn (User $u) => (bool) $entries->get($u->id)?->isOnLeave())->count(),
            'missing' => $roster->reject($hasLogged)->values(),
        ];
    }

    /**
     * Standup attendance over the last STANDUP_DAYS weekdays, ending today:
     * per day, how many marked rows were "attended". Like the tasksheet
     * filters, an unmarked row and a full-day leave are not counted as missed.
     *
     * @return array{days: list<array{date: Carbon, attended: int, marked: int, today: bool}>, attended: int, marked: int, rate: ?int}
     */
    public function standupAttendance(Team $team, ?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();

        $days = [];
        for ($day = $today->copy(); count($days) < self::STANDUP_DAYS; $day->subDay()) {
            if ($day->isWeekday()) {
                $days[] = $day->copy();
            }
        }
        $days = array_reverse($days);

        // whereDate bounds: the `date` cast stores a midnight timestamp (see TasksheetService).
        $byDay = TasksheetEntry::query()
            ->where('team_id', $team->id)
            ->whereDate('date', '>=', $days[0]->toDateString())
            ->whereDate('date', '<=', $today->toDateString())
            ->get(['id', 'date', 'standup_attended', 'leave_type'])
            ->groupBy(fn (TasksheetEntry $e) => $e->date->toDateString());

        $series = array_map(function (Carbon $day) use ($byDay, $today) {
            $marked = collect($byDay->get($day->toDateString(), []))
                ->filter(fn (TasksheetEntry $e) => ! $e->isStandupUnmarked() && $e->acceptsStandupAttendance());

            return [
                'date' => $day,
                'attended' => $marked->filter(fn (TasksheetEntry $e) => $e->attendedStandup())->count(),
                'marked' => $marked->count(),
                'today' => $day->isSameDay($today),
            ];
        }, $days);

        $attended = array_sum(array_column($series, 'attended'));
        $marked = array_sum(array_column($series, 'marked'));

        return [
            'days' => $series,
            'attended' => $attended,
            'marked' => $marked,
            'rate' => $marked > 0 ? (int) round($attended / $marked * 100) : null,
        ];
    }

    /**
     * Open work: counted (never archived) tasks that are not done yet.
     *
     * @return Builder<Task>
     */
    private function openTasks(): Builder
    {
        return Task::query()->counted()->where('status', '!=', 'done');
    }

    /**
     * Add a member, restoring a previous membership rather than duplicating it.
     */
    public function addMember(Team $team, int|string $userId): User
    {
        $team->memberRecords()->syncWithoutDetaching([$userId => ['left_at' => null]]);

        return User::findOrFail($userId);
    }

    /** Soft leave: stamp the departure, keep the row. */
    public function removeMember(Team $team, User $user): void
    {
        $team->memberRecords()->updateExistingPivot($user->id, ['left_at' => now()]);
    }

    /** Assign or clear the team lead — any active user, regardless of role. */
    public function updateLead(Team $team, int|string|null $userId): Team
    {
        $team->update(['team_lead_id' => $userId ?: null]);

        return $team->load('teamLead');
    }

    /**
     * Active users not already on the team, for the "add member" picker.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(Team $team): Collection
    {
        $currentIds = $team->relationLoaded('members')
            ? $team->members->pluck('id')
            : $team->members()->pluck('users.id');

        return User::active()
            ->whereNotIn('id', $currentIds)
            ->orderBy('name')
            ->get();
    }

    /** Any active user may lead a team — role is irrelevant. */
    public function leadCandidates(): Collection
    {
        return User::active()->orderBy('name')->get();
    }

    /**
     * A team that owns releases is archived, never hard-deleted — deleting it
     * would cascade away release history.
     */
    public function isDeletable(Team $team): bool
    {
        return ! $team->releases()->exists();
    }

    public function archive(Team $team): Team
    {
        $team->update(['archived_at' => now()]);

        return $team;
    }

    public function restore(Team $team): Team
    {
        $team->update(['archived_at' => null]);

        return $team;
    }
}

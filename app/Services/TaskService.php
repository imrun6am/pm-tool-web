<?php

namespace App\Services;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tasks and subtasks.
 *
 * Two rules live here rather than in either controller: a new task defaults to
 * the initial status, and nesting stops at one level — a subtask may not have
 * subtasks of its own. `created_by` is stamped only on creation, never on an
 * update, so an edit does not rewrite who filed the work.
 *
 * A release is optional. A top-level task may be linked, relinked or unlinked;
 * a subtask always carries its parent's release (or none) and follows it.
 */
class TaskService
{
    /** The attributes a task write accepts from a request. */
    private const WRITABLE = ['release_id', 'team_id', 'title', 'description', 'status', 'assignee_id', 'due_date', 'phase'];

    public const DEFAULT_STATUS = 'todo';

    /** The `release_id` / `team_id` filter value that selects tasks with none set. */
    public const NONE = 'none';

    /**
     * The cross-release task query, limited to what `$viewer` may see when given.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    public function filtered(array $filters = [], ?User $viewer = null): Builder
    {
        return Task::query()
            ->when($viewer, fn ($q) => $q->visibleTo($viewer))
            ->with(['release.project', 'team', 'assignee', 'creator'])
            ->withCount(['comments', 'subtasks'])
            ->unless($filters['include_subtasks'] ?? false, fn ($q) => $q->whereNull('parent_id'))
            ->when($filters['release_id'] ?? null, fn ($q, $id) => self::whereIdOrNone($q, 'release_id', $id))
            ->when($filters['team_id'] ?? null, fn ($q, $id) => self::whereIdOrNone($q, 'team_id', $id))
            ->when($filters['assignee_id'] ?? null, fn ($q, $id) => $q->where('assignee_id', $id))
            ->when($filters['parent_id'] ?? null, fn ($q, $id) => $q->where('parent_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['phase'] ?? null, fn ($q, $phase) => $q->where('phase', $phase))
            ->when($filters['due_before'] ?? null, fn ($q, $d) => $q->whereDate('due_date', '<=', $d))
            ->when($filters['due_after'] ?? null, fn ($q, $d) => $q->whereDate('due_date', '>=', $d))
            ->when($filters['overdue'] ?? false, fn ($q) => $q
                ->whereNotIn('status', Task::DONE_STATUSES)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString()))
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * A release's top-level tasks with their subtasks — the shape a release
     * detail screen draws — limited to what `$viewer` may see when given.
     *
     * @return Builder<Task>
     */
    public function forRelease(Release $release, array $filters = [], ?User $viewer = null): Builder
    {
        return $release->rootTasks()
            ->when($viewer, fn ($q) => $q->visibleTo($viewer))
            ->with(['assignee', 'creator', 'subtasks.assignee'])
            ->withCount(['comments', 'subtasks'])
            ->when($filters['assignee_id'] ?? null, fn ($q, $id) => $q->where('assignee_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['phase'] ?? null, fn ($q, $phase) => $q->where('phase', $phase))
            ->getQuery();
    }

    /**
     * Narrow a task query on `release_id` / `team_id` to one id, or to tasks
     * with none set for NONE.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public static function whereIdOrNone(Builder $query, string $column, int|string $value): Builder
    {
        return $value === self::NONE
            ? $query->whereNull($column)
            : $query->where($column, (int) $value);
    }

    /**
     * A top-level task, linked to `release_id` when one is given. Its team is
     * resolved by teamFor().
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $author): Task
    {
        $releaseId = isset($attributes['release_id']) ? (int) $attributes['release_id'] : null;

        return Task::create($this->attributes($attributes, [
            'release_id' => $releaseId,
            'team_id' => self::teamFor(
                $releaseId,
                isset($attributes['team_id']) ? (int) $attributes['team_id'] : null,
                isset($attributes['assignee_id']) ? (int) $attributes['assignee_id'] : null,
            ),
            'parent_id' => null,
            'position' => self::nextPosition($releaseId),
            'created_by' => $author->id,
        ]));
    }

    /**
     * A top-level task pinned to the given release, whatever the request says.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForRelease(Release $release, array $attributes, User $author): Task
    {
        return $this->create(['release_id' => $release->id] + $attributes, $author);
    }

    /**
     * A new task's team: its release's team when linked; otherwise the team
     * asked for, else the assignee's team when they are on exactly one.
     */
    public static function teamFor(?int $releaseId, ?int $teamId = null, ?int $assigneeId = null): ?int
    {
        if ($releaseId) {
            return Release::whereKey($releaseId)->value('team_id');
        }

        if ($teamId || ! $assigneeId) {
            return $teamId;
        }

        $teams = User::find($assigneeId)?->teams()->pluck('teams.id') ?? collect();

        return $teams->count() === 1 ? $teams->first() : null;
    }

    /** The next top-level position among the release's tasks (or the unlinked ones). */
    public static function nextPosition(?int $releaseId): int
    {
        return Task::whereNull('parent_id')->where('release_id', $releaseId)->count();
    }

    /**
     * Add a subtask. Nesting is one level only — a subtask of a subtask is
     * refused rather than silently re-parented.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createSubtask(Task $parent, array $attributes, User $author): Task
    {
        abort_if($parent->isSubtask(), 422, 'A subtask cannot have its own subtasks.');

        return $parent->subtasks()->create($this->attributes($attributes, [
            'release_id' => $parent->release_id,
            'team_id' => $parent->team_id,
            'parent_id' => $parent->id,
            'position' => $parent->subtasks()->count(),
            'created_by' => $author->id,
        ]));
    }

    /**
     * `release_id` and `team_id` are applied only when present, so a partial
     * update leaves them alone. A linked task's team is always its release's;
     * unlinking keeps the team it had. A subtask's release and team are its
     * parent's and are never set here; a top-level task's subtasks follow it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Task $task, array $attributes): Task
    {
        if ($task->isSubtask()) {
            unset($attributes['release_id'], $attributes['team_id']);
        }

        $releaseId = array_key_exists('release_id', $attributes) ? $attributes['release_id'] : $task->release_id;
        if ($releaseId) {
            $attributes['team_id'] = self::teamFor((int) $releaseId);
        }

        DB::transaction(function () use ($task, $attributes) {
            $task->update($this->attributes($attributes));

            if ($task->wasChanged(['release_id', 'team_id'])) {
                $task->subtasks()->toBase()->update(['release_id' => $task->release_id, 'team_id' => $task->team_id]);
                $task->unsetRelation('release')->unsetRelation('team');
            }
        });

        return $task;
    }

    public function changeStatus(Task $task, string $status): Task
    {
        $task->update(['status' => $status]);

        return $task;
    }

    /**
     * Assignable people for a task: the release team's active members (every
     * active user when the task has no release; only their own teammates for a
     * developer or QA viewer) plus any current assignee who has since left, so
     * nothing renders blank.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(Task $task, ?User $viewer = null): Collection
    {
        $current = collect([$task->assignee])
            ->merge($task->subtasks->map->assignee)
            ->filter();

        $pool = $task->release
            ? $task->release->team->members()->active()
            : User::active();

        if ($viewer?->hasLimitedAccess()) {
            $pool = $pool->teammatesOf($viewer);
        }

        return $pool->orderBy('name')->get()
            ->concat($current)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Writable attributes merged with the caller's extras, defaulting the status.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function attributes(array $attributes, array $extra = []): array
    {
        $writable = array_intersect_key($attributes, array_flip(self::WRITABLE));
        $writable['status'] = $writable['status'] ?? self::DEFAULT_STATUS;

        return array_merge($writable, $extra);
    }
}

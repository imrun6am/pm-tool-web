<?php

namespace App\Services;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tasks and subtasks.
 *
 * Two rules live here rather than in either controller: a new task defaults to
 * the initial status, and nesting stops at one level — a subtask may not have
 * subtasks of its own. `created_by` is stamped only on creation, never on an
 * update, so an edit does not rewrite who filed the work.
 */
class TaskService
{
    /** The attributes a task write accepts from a request. */
    private const WRITABLE = ['title', 'description', 'status', 'assignee_id', 'due_date', 'phase'];

    public const DEFAULT_STATUS = 'todo';

    /**
     * The cross-release task query.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    public function filtered(array $filters = []): Builder
    {
        return Task::query()
            ->with(['release.project', 'assignee', 'creator'])
            ->withCount(['comments', 'subtasks'])
            ->unless($filters['include_subtasks'] ?? false, fn ($q) => $q->whereNull('parent_id'))
            ->when($filters['release_id'] ?? null, fn ($q, $id) => $q->where('release_id', $id))
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
     * detail screen draws.
     *
     * @return Builder<Task>
     */
    public function forRelease(Release $release, array $filters = []): Builder
    {
        return $release->rootTasks()
            ->with(['assignee', 'creator', 'subtasks.assignee'])
            ->withCount(['comments', 'subtasks'])
            ->when($filters['assignee_id'] ?? null, fn ($q, $id) => $q->where('assignee_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['phase'] ?? null, fn ($q, $phase) => $q->where('phase', $phase))
            ->getQuery();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForRelease(Release $release, array $attributes, User $author): Task
    {
        return $release->tasks()->create($this->attributes($attributes, [
            'parent_id' => null,
            'position' => $release->rootTasks()->count(),
            'created_by' => $author->id,
        ]));
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
            'parent_id' => $parent->id,
            'position' => $parent->subtasks()->count(),
            'created_by' => $author->id,
        ]));
    }

    /** @param array<string, mixed> $attributes */
    public function update(Task $task, array $attributes): Task
    {
        $task->update($this->attributes($attributes));

        return $task;
    }

    public function changeStatus(Task $task, string $status): Task
    {
        $task->update(['status' => $status]);

        return $task;
    }

    /**
     * Assignable people for a task: the release team's active members plus any
     * current assignee who has since left, so nothing renders blank.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(Task $task): Collection
    {
        $current = collect([$task->assignee])
            ->merge($task->subtasks->map->assignee)
            ->filter();

        return $task->release->team->members()->active()->orderBy('name')->get()
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

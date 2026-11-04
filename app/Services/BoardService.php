<?php

namespace App\Services;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use App\Models\WipLimit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The kanban board.
 *
 * Every status column is present in the result even when it holds no cards — a
 * client has to be able to render an empty column as a drop target, and
 * inferring the column set from whichever cards happen to exist would make
 * empty columns vanish.
 */
class BoardService
{
    /** The most cards one bulk delete may take, to bound its transaction. */
    public const MAX_BULK_DELETE = 1000;

    /** The ways the board can group a column's cards. */
    public const GROUPS = ['assignee', 'priority'];

    /** @return array<string, int> a team's column limits, by status */
    public function wipLimits(int $teamId): array
    {
        return WipLimit::where('team_id', $teamId)->pluck('max_tasks', 'status')->all();
    }

    /** Set a column's limit, or clear it with null. */
    public function setWipLimit(int $teamId, string $status, ?int $max): void
    {
        if ($max === null) {
            WipLimit::where('team_id', $teamId)->where('status', $status)->first()?->delete();

            return;
        }

        WipLimit::updateOrCreate(['team_id' => $teamId, 'status' => $status], ['max_tasks' => $max]);
    }

    /**
     * Sort a column's cards into their groups, and say which group each is in.
     * Priority follows its own order; people go by name; "none" comes last.
     *
     * @param  Collection<int, Task>  $cards
     * @return Collection<int, Task>
     */
    public function grouped(Collection $cards, string $group): Collection
    {
        return $cards
            ->sortBy(fn (Task $task) => $group === 'priority'
                ? ($task->priority ? array_search($task->priority, array_keys(Task::PRIORITIES), true) : 99)
                : ($task->assignee ? '0'.$task->assignee->name : '1'), SORT_NATURAL)
            ->each(fn (Task $task) => $task->setAttribute('lane', $group === 'priority'
                ? ($task->priorityLabel() ?? 'No priority')
                : ($task->assignee?->name ?? 'Unassigned')))
            ->values();
    }

    /**
     * Delete cards together: all of them or none. Each goes through Eloquent so
     * the model's hook removes its subtasks and comments and every deleted task
     * is written to the activity log.
     *
     * @param  iterable<int, Task>  $tasks
     * @return array{deleted_ids: array<int, int>, subtasks_deleted: int}
     */
    public function deleteCards(iterable $tasks): array
    {
        return DB::transaction(function () use ($tasks) {
            $deletedIds = [];
            $subtasks = 0;

            foreach ($tasks as $task) {
                $subtasks += $task->subtasks()->count();
                $task->delete();
                $deletedIds[] = $task->id;
            }

            return ['deleted_ids' => $deletedIds, 'subtasks_deleted' => $subtasks];
        });
    }

    /**
     * The board's cards grouped into columns, keyed by status.
     *
     * Returns `[status => Collection<Task>]` so the Blade view can index by
     * status directly; the API adds the label, color, and count around it.
     *
     * `release_id`, `team_id` and `assignee_id` are ids, or TaskService::NONE for
     * tasks with none set. Filters combine, within what `$viewer` may see when given.
     *
     * @param  array{release_id?: int|string|null, team_id?: int|string|null, assignee_id?: int|string|null, priority?: ?string, label_id?: ?int, blocked?: bool}  $filters
     * @return array<string, Collection<int, Task>>
     */
    public function columns(array $filters = [], ?User $viewer = null): array
    {
        $tasks = Task::query()
            ->when($viewer, fn ($q) => $q->visibleTo($viewer))
            ->whereNull('parent_id') // top-level tasks are the cards
            ->with(['release.project', 'team', 'assignee', 'subtasks', 'labels'])
            ->withExists('openBlockers as has_open_blockers')
            ->withMax('statusChanges', 'changed_at')
            ->tap(fn ($q) => TaskService::whereFields($q, $filters))
            ->withCount('comments')
            ->when($filters['release_id'] ?? null, fn ($q, $id) => TaskService::whereIdOrNone($q, 'release_id', $id))
            ->when($filters['team_id'] ?? null, fn ($q, $id) => TaskService::whereIdOrNone($q, 'team_id', $id))
            ->when($filters['assignee_id'] ?? null, fn ($q, $id) => TaskService::whereIdOrNone($q, 'assignee_id', $id))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return collect(array_keys(Task::STATUSES))
            ->mapWithKeys(fn (string $status) => [$status => $tasks->where('status', $status)->values()])
            ->all();
    }

    /**
     * Quick-add straight onto a column, linked to a release or to none. A
     * linked card takes the release's team; an unlinked one the given team, else
     * its assignee's when they are on exactly one.
     */
    public function quickAdd(?Release $release, string $title, string $status, User $author, ?int $teamId = null, ?int $assigneeId = null): Task
    {
        return Task::create([
            'release_id' => $release?->id,
            'team_id' => TaskService::teamFor($release?->id, $teamId, $assigneeId),
            'title' => $title,
            'status' => $status,
            'assignee_id' => $assigneeId,
            'parent_id' => null,
            'created_by' => $author->id,
            'position' => TaskService::nextPosition($release?->id),
        ]);
    }

    /**
     * Give a card an assignee, or none. Only that column changes:
     * TaskService::update() would default a missing status back to the first
     * column. The activity log and the assignment notice follow the model write.
     */
    public function assign(Task $task, ?int $assigneeId): Task
    {
        $task->update(['assignee_id' => $assigneeId]);

        return $task;
    }

    /**
     * A drag: set the card's status and renumber the target column together, in
     * one transaction, so the board can never be observed half-moved.
     *
     * Only cards `$viewer` may see are renumbered, so a column order can't be
     * used to touch another team's tasks.
     *
     * @param  array<int, int|string>  $orderedIds  the target column's new order
     */
    public function move(Task $task, string $status, array $orderedIds = [], ?User $viewer = null): Task
    {
        DB::transaction(function () use ($task, $status, $orderedIds, $viewer) {
            $task->update(['status' => $status]);

            foreach ($orderedIds as $index => $id) {
                Task::whereKey($id)->whereNull('parent_id')
                    ->when($viewer, fn ($q) => $q->visibleTo($viewer))
                    ->update(['position' => $index]);
            }
        });

        return $task;
    }
}

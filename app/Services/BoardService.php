<?php

namespace App\Services;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
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
    /**
     * The board's cards grouped into columns, keyed by status.
     *
     * Returns `[status => Collection<Task>]` so the Blade view can index by
     * status directly; the API adds the label, color, and count around it.
     *
     * `release_id` and `team_id` are ids, or TaskService::NONE for tasks with
     * none set. Filters combine, within what `$viewer` may see when given.
     *
     * @param  array{release_id?: int|string|null, team_id?: int|string|null, assignee_id?: ?int}  $filters
     * @return array<string, Collection<int, Task>>
     */
    public function columns(array $filters = [], ?User $viewer = null): array
    {
        $tasks = Task::query()
            ->when($viewer, fn ($q) => $q->visibleTo($viewer))
            ->whereNull('parent_id') // top-level tasks are the cards
            ->with(['release.project', 'team', 'assignee', 'subtasks'])
            ->withCount('comments')
            ->when($filters['release_id'] ?? null, fn ($q, $id) => TaskService::whereIdOrNone($q, 'release_id', $id))
            ->when($filters['team_id'] ?? null, fn ($q, $id) => TaskService::whereIdOrNone($q, 'team_id', $id))
            ->when($filters['assignee_id'] ?? null, fn ($q, $id) => $q->where('assignee_id', $id))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return collect(array_keys(Task::STATUSES))
            ->mapWithKeys(fn (string $status) => [$status => $tasks->where('status', $status)->values()])
            ->all();
    }

    /**
     * Quick-add straight onto a column, linked to a release or to none. A
     * linked card takes the release's team; an unlinked one the given team.
     */
    public function quickAdd(?Release $release, string $title, string $status, User $author, ?int $teamId = null): Task
    {
        return Task::create([
            'release_id' => $release?->id,
            'team_id' => $release ? $release->team_id : $teamId,
            'title' => $title,
            'status' => $status,
            'parent_id' => null,
            'created_by' => $author->id,
            'position' => TaskService::nextPosition($release?->id),
        ]);
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

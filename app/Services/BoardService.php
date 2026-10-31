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
     * @param  array{release_id?: ?int, assignee_id?: ?int}  $filters
     * @return array<string, Collection<int, Task>>
     */
    public function columns(array $filters = []): array
    {
        $tasks = Task::query()
            ->whereNull('parent_id') // top-level tasks are the cards
            ->with(['release.project', 'assignee', 'subtasks'])
            ->withCount('comments')
            ->when($filters['release_id'] ?? null, fn ($q, $id) => $q->where('release_id', $id))
            ->when($filters['assignee_id'] ?? null, fn ($q, $id) => $q->where('assignee_id', $id))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return collect(array_keys(Task::STATUSES))
            ->mapWithKeys(fn (string $status) => [$status => $tasks->where('status', $status)->values()])
            ->all();
    }

    /** Quick-add straight onto a column. */
    public function quickAdd(Release $release, string $title, string $status, User $author): Task
    {
        return $release->tasks()->create([
            'title' => $title,
            'status' => $status,
            'parent_id' => null,
            'created_by' => $author->id,
            'position' => $release->rootTasks()->count(),
        ]);
    }

    /**
     * A drag: set the card's status and renumber the target column together, in
     * one transaction, so the board can never be observed half-moved.
     *
     * @param  array<int, int|string>  $orderedIds  the target column's new order
     */
    public function move(Task $task, string $status, array $orderedIds = []): Task
    {
        DB::transaction(function () use ($task, $status, $orderedIds) {
            $task->update(['status' => $status]);

            foreach ($orderedIds as $index => $id) {
                Task::whereKey($id)->whereNull('parent_id')->update(['position' => $index]);
            }
        });

        return $task;
    }
}

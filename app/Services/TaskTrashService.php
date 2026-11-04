<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deleted tasks wait here until they're restored or purged. Subtasks that went
 * with their parent aren't listed: they come back with it.
 */
class TaskTrashService
{
    /** @return Builder<Task> */
    public function listing(): Builder
    {
        return Task::onlyTrashed()
            ->where('trashed_with_parent', false)
            ->with(['release', 'team', 'assignee', 'deletedBy'])
            ->withExists('openBlockers as has_open_blockers')
            ->orderByDesc('deleted_at')
            ->orderByDesc('id');
    }

    /**
     * Bring tasks back, with the subtasks that went with them. A subtask whose
     * parent is also in the trash brings its parent back, or it would have
     * nowhere to show.
     *
     * @param  iterable<int, Task>  $tasks
     * @return array<int, int> the ids asked for
     */
    public function restore(iterable $tasks): array
    {
        return DB::transaction(function () use ($tasks) {
            $ids = [];

            foreach ($tasks as $task) {
                if ($task->trashed()) {
                    if ($task->parent_id) {
                        Task::onlyTrashed()->whereKey($task->parent_id)->first()?->restore();
                    }

                    // Bringing the parent back may have brought this one too.
                    $task->refresh();
                    if ($task->trashed()) {
                        $task->restore();
                    }
                }

                $ids[] = $task->id;
            }

            return $ids;
        });
    }

    /** Remove a trashed task for good, with its subtasks, comments, time and history. */
    public function purge(Task $task): void
    {
        DB::transaction(fn () => $task->forceDelete());
    }
}

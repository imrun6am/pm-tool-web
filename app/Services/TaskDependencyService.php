<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What holds a task up: a blocked flag with a reason, and other tasks it has to
 * wait for. Either one makes it blocked (Task::isBlocked).
 */
class TaskDependencyService
{
    public function flag(Task $task, string $reason): Task
    {
        // Keep the original time when only the reason changes.
        $task->update(['blocked_at' => $task->blocked_at ?? now(), 'blocked_reason' => $reason]);

        return $task;
    }

    public function clearFlag(Task $task): Task
    {
        $task->update(['blocked_at' => null, 'blocked_reason' => null]);

        return $task;
    }

    public function add(Task $task, Task $blocker): void
    {
        $task->blockers()->syncWithoutDetaching([$blocker->id]);
    }

    public function remove(Task $task, Task $blocker): void
    {
        $task->blockers()->detach($blocker->id);
    }

    /** Would making `$task` wait for `$blocker` close a loop? Follows what `$blocker` already waits for. */
    public function wouldCycle(Task $task, Task $blocker): bool
    {
        $seen = [];
        $frontier = [$blocker->id];

        while ($frontier !== []) {
            if (in_array($task->id, $frontier, true)) {
                return true;
            }

            $seen = array_merge($seen, $frontier);
            $frontier = DB::table('task_dependencies')
                ->whereIn('task_id', $frontier)
                ->pluck('depends_on_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->diff($seen)
                ->values()
                ->all();
        }

        return false;
    }

    /**
     * The task's links that `$viewer` may see, loaded on the task so a resource
     * can show them. A developer never learns about another team's tasks from
     * here; `Task::isBlocked()` still counts them.
     */
    public function loadLinks(Task $task, User $viewer): Task
    {
        return $task
            ->setRelation('blockers', $this->visible($task->blockers()->with('assignee')->get(), $viewer))
            ->setRelation('blocking', $this->visible($task->blocking()->with('assignee')->get(), $viewer));
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    private function visible(Collection $tasks, User $viewer): Collection
    {
        return $tasks->filter(fn (Task $t) => $t->isVisibleTo($viewer))->values();
    }
}

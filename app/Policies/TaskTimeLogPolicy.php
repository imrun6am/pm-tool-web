<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;

/**
 * Anyone who can see a task can log their own hours on it and see its entries,
 * except viewers, who do no work and only see totals. People change their own
 * entries for a week; after that, or for someone else's, it takes a lead.
 */
class TaskTimeLogPolicy
{
    public const EDIT_WINDOW_DAYS = 7;

    public function viewEntries(User $user, Task $task): bool
    {
        return ! $user->isViewer() && $task->isVisibleTo($user);
    }

    public function create(User $user, Task $task): bool
    {
        return ! $user->isViewer() && $task->isVisibleTo($user);
    }

    public function update(User $user, TaskTimeLog $log): bool
    {
        // Its task is in the trash: the entry waits with it.
        if (! $log->task) {
            return false;
        }

        return $this->correct($user, $log->task)
            || ($log->user_id === $user->id
                && $log->task->isVisibleTo($user)
                && $log->created_at->gte(now()->subDays(self::EDIT_WINDOW_DAYS)));
    }

    public function delete(User $user, TaskTimeLog $log): bool
    {
        return $this->update($user, $log);
    }

    /** May this user change anyone's hours on the task: org-level leads, or the team's own lead. */
    public function correct(User $user, Task $task): bool
    {
        return $user->canOverseeAllTeams() || ($task->team && $user->leadsTeam($task->team));
    }
}

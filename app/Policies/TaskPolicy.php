<?php

namespace App\Policies;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;

/**
 * Tasks are open to every signed-in user, except that developers and QA are
 * kept to their own teams' work (see Task::scopeVisibleTo).
 */
class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $task->isVisibleTo($user);
    }

    /** Editing, status changes, moves, subtasks and comments. */
    public function update(User $user, Task $task): bool
    {
        return $task->isVisibleTo($user);
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->isVisibleTo($user);
    }

    /**
     * Adding a task, optionally into a release. Developers and QA may only
     * file work into their own teams' releases.
     */
    public function create(User $user, ?Release $release = null): bool
    {
        return ! $release
            || ! $user->hasLimitedAccess()
            || $user->currentTeamIds()->contains($release->team_id);
    }
}

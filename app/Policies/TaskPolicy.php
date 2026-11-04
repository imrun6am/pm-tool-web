<?php

namespace App\Policies;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;

/**
 * Tasks are open to every signed-in user, except that developers and QA are
 * kept to their own teams' work (see Task::scopeVisibleTo), and only admins
 * may delete.
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

    /** Deleting a task or subtask, from any page or the API: admins only. */
    public function delete(User $user, Task $task): bool
    {
        return $user->isAdmin() && $task->isVisibleTo($user);
    }

    /** Seeing the trash, restoring from it and purging it. */
    public function manageTrash(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Deleting cards from the board, singly or in bulk. Without a task this
     * answers whether the user may use the feature at all; with one, whether
     * this task can go. Only top-level tasks are cards.
     */
    public function deleteFromBoard(User $user, ?Task $task = null): bool
    {
        return $task ? ! $task->isSubtask() && $this->delete($user, $task) : $user->isAdmin();
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

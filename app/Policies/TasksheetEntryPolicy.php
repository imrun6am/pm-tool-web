<?php

namespace App\Policies;

use App\Models\TasksheetEntry;
use App\Models\User;

class TasksheetEntryPolicy
{
    /**
     * A member may save their own row — but only while they are still on the
     * team (someone removed from a team can no longer fill or edit its rows;
     * their earlier rows remain visible). Leads (admin, CTO, team lead) may
     * correct anyone's. Also authorized against unsaved rows (upsert).
     */
    public function update(User $user, TasksheetEntry $entry): bool
    {
        if ($user->isLead()) {
            return true;
        }

        return $entry->user_id === $user->id
            && $entry->team->members()->whereKey($user->id)->exists();
    }

    /**
     * Verifying standup attendance is leads only. Deliberately not routed
     * through `update`, which also grants a member their own row — a member
     * must never be able to vouch for their own attendance.
     */
    public function verifyStandup(User $user, TasksheetEntry $entry): bool
    {
        return $user->isLead();
    }
}

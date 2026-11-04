<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class DailyStandupPolicy
{
    /**
     * The same people who may correct a row on that team's tasksheet: leads and
     * the team's current members. Viewers read but don't change anything.
     */
    public function generate(User $user, Team $team): bool
    {
        return $user->isLead() || $team->members()->whereKey($user->id)->exists();
    }
}

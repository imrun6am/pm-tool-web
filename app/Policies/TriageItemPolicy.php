<?php

namespace App\Policies;

use App\Models\TriageItem;
use App\Models\User;

/** Anyone can report. Leads decide: org-level leads on anything, team leads on their own team's or unassigned items. */
class TriageItemPolicy
{
    public function decide(User $user, TriageItem $item): bool
    {
        return $user->isLead()
            && (! $item->team_id || $user->canAccessTeamPerformance($item->team));
    }
}

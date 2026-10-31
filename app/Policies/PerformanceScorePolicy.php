<?php

namespace App\Policies;

use App\Models\PerformanceScore;
use App\Models\User;

/**
 * Authorizes acting on a performance score. The route is already behind the
 * `lead` middleware (leadership tier only); this policy adds team scoping: a
 * team lead may only act on the team they are the assigned lead of, while
 * org-level leads (admin, CTO, tech lead) may act on any team.
 */
class PerformanceScorePolicy
{
    /** Create or update a score — authorized against the (possibly unsaved) row's team. */
    public function update(User $user, PerformanceScore $score): bool
    {
        return $score->team !== null && $user->canAccessTeamPerformance($score->team);
    }

    public function view(User $user, PerformanceScore $score): bool
    {
        return $this->update($user, $score);
    }
}

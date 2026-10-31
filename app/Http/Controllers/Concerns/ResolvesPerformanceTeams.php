<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

trait ResolvesPerformanceTeams
{
    /**
     * Active teams this user may act on for performance, teams they lead first.
     * Org-level leads see every team; a team lead sees only teams they lead.
     */
    protected function accessiblePerformanceTeams(User $viewer): Collection
    {
        return Team::active()->orderBy('name')->get()
            ->filter(fn (Team $t) => $viewer->canAccessTeamPerformance($t))
            ->sortBy(fn (Team $t) => [$viewer->leadsTeam($t) ? 0 : 1, $t->name])
            ->values();
    }

    /** Resolve the selected team from a `?team=` id within the accessible set. */
    protected function resolvePerformanceTeam(Collection $teams, ?int $teamId): ?Team
    {
        return ($teamId ? $teams->firstWhere('id', $teamId) : null) ?? $teams->first();
    }
}

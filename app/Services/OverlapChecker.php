<?php

namespace App\Services;

use App\Models\Release;
use Illuminate\Support\Collection;

/**
 * Central definition of "two releases owned by the same team overlap in time".
 *
 * Two windows overlap when one's start is on/before the other's end AND its end
 * is on/after the other's start. This is used both to warn on save and to flag
 * conflicts on the dashboard, so the rule lives in exactly one place.
 */
class OverlapChecker
{
    /**
     * Releases owned by $teamId whose window overlaps [$start, $end],
     * excluding the release being edited ($exceptReleaseId).
     *
     * @return Collection<int, Release>
     */
    public function conflictsFor(
        int $teamId,
        string $start,
        string $end,
        ?int $exceptReleaseId = null
    ): Collection {
        return Release::query()
            ->with(['project', 'team'])
            ->where('team_id', $teamId)
            ->whereNull('completed_at') // a shipped release no longer books the team
            ->when($exceptReleaseId, fn ($q) => $q->whereKeyNot($exceptReleaseId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Do two date windows overlap? Pure predicate, easy to unit test.
     */
    public static function windowsOverlap(
        string $startA,
        string $endA,
        string $startB,
        string $endB
    ): bool {
        return $startA <= $endB && $endA >= $startB;
    }

    /**
     * Given a set of releases (already loaded), return the ids of those that
     * overlap at least one other release owned by the same team. Used by the
     * dashboard to highlight conflicting bars in a single pass.
     *
     * @param  Collection<int, Release>  $releases
     * @return array<int, bool> keyed by release id
     */
    public function flagConflicts(Collection $releases): array
    {
        $flags = [];

        $byTeam = $releases->groupBy('team_id');

        foreach ($byTeam as $teamReleases) {
            $list = $teamReleases->values();
            $count = $list->count();

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $list[$i];
                    $b = $list[$j];

                    if (self::windowsOverlap(
                        $a->start_date->toDateString(),
                        $a->end_date->toDateString(),
                        $b->start_date->toDateString(),
                        $b->end_date->toDateString()
                    )) {
                        $flags[$a->id] = true;
                        $flags[$b->id] = true;
                    }
                }
            }
        }

        return $flags;
    }
}

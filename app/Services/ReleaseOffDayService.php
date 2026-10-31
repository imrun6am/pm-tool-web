<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseOffDay;
use Illuminate\Support\Collection;

/**
 * Non-working days inside a release window. Off-days are what turn a release's
 * calendar span into its working-day count, so they belong to the plan rather
 * than to a calendar of their own.
 */
class ReleaseOffDayService
{
    /**
     * @param  array{date: string, reason?: ?string}  $attributes
     */
    public function add(Release $release, array $attributes): ReleaseOffDay
    {
        return $release->offDays()->create($attributes);
    }

    /**
     * Mark every Saturday and Sunday in the window that is not already off.
     *
     * Returns how many were added, so the caller can say "all weekends were
     * already marked" rather than claiming a no-op succeeded.
     */
    public function markWeekends(Release $release): int
    {
        $existing = $release->offDays()->get()
            ->map(fn (ReleaseOffDay $o) => $o->date->toDateString())
            ->all();

        $added = 0;
        $cursor = $release->start_date->copy();

        while ($cursor->lte($release->end_date)) {
            if ($cursor->isWeekend() && ! in_array($cursor->toDateString(), $existing, true)) {
                $release->offDays()->create([
                    'date' => $cursor->toDateString(),
                    'reason' => 'Weekend',
                ]);
                $added++;
            }

            $cursor->addDay();
        }

        return $added;
    }

    /** Remove an off-day, refusing one that belongs to a different release. */
    public function remove(Release $release, ReleaseOffDay $offDay): void
    {
        abort_unless($offDay->release_id === $release->id, 404);

        $offDay->delete();
    }

    /** @return Collection<int, ReleaseOffDay> */
    public function forRelease(Release $release): Collection
    {
        return $release->offDays()->get();
    }
}

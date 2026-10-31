<?php

namespace App\Support;

use App\Models\PerformanceCompetency;
use Illuminate\Support\Carbon;

/**
 * Pure period math for performance scoring. A competency's cadence maps a
 * calendar date onto a normalized period: a single day for daily cadence, or a
 * Monday–Sunday ISO week for weekly cadence. Weeks are Monday-anchored
 * explicitly (not locale-dependent) so every environment agrees.
 *
 * Kept static and dependency-free so it can be unit-tested without the database.
 */
class PerformancePeriod
{
    /**
     * Normalize a date for a cadence into a period triple.
     *
     * @return array{type: string, start: Carbon, end: Carbon}
     */
    public static function normalize(string $cadence, Carbon $date): array
    {
        if ($cadence === PerformanceCompetency::CADENCE_WEEKLY) {
            return [
                'type' => PerformanceCompetency::CADENCE_WEEKLY,
                'start' => $date->copy()->startOfWeek(Carbon::MONDAY),
                'end' => $date->copy()->endOfWeek(Carbon::SUNDAY),
            ];
        }

        return [
            'type' => PerformanceCompetency::CADENCE_DAILY,
            'start' => $date->copy()->startOfDay(),
            'end' => $date->copy()->startOfDay(),
        ];
    }

    /** The Monday–Sunday week containing a date (the analytics roll-up window). */
    public static function week(Carbon $date): array
    {
        return self::normalize(PerformanceCompetency::CADENCE_WEEKLY, $date);
    }

    /**
     * A series of recent weeks ending with the week containing $end, oldest
     * first — used to build trend sparklines.
     *
     * @return array<int, array{start: Carbon, end: Carbon, label: string}>
     */
    public static function recentWeeks(Carbon $end, int $count): array
    {
        $weeks = [];
        $cursor = $end->copy()->startOfWeek(Carbon::MONDAY);

        for ($i = $count - 1; $i >= 0; $i--) {
            $start = $cursor->copy()->subWeeks($i);
            $weeks[] = [
                'start' => $start,
                'end' => $start->copy()->endOfWeek(Carbon::SUNDAY),
                'label' => $start->format('M j'),
            ];
        }

        return $weeks;
    }

    /** Human label for the week containing a date, e.g. "Jul 21 – 27, 2026". */
    public static function weekLabel(Carbon $date): string
    {
        $w = self::week($date);

        return $w['start']->format('M j').' – '.$w['end']->format('M j, Y');
    }
}

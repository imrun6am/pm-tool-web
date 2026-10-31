<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Pure date→pixel math for the dashboard timeline. Kept dependency-free and
 * static so it can be unit-tested without touching the database.
 */
class Timeline
{
    /**
     * Position a [start, end] window inside a [rangeStart, rangeEnd] axis.
     * Returns left offset % and width %, both clamped to the visible axis, plus
     * a `visible` flag that is false when the window does not intersect the axis.
     *
     * @return array{offset: float, width: float, visible: bool}
     */
    public static function segment(Carbon $start, Carbon $end, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $totalDays = $rangeStart->diffInDays($rangeEnd) + 1;

        if ($totalDays <= 0 || $end->lt($rangeStart) || $start->gt($rangeEnd)) {
            return ['offset' => 0.0, 'width' => 0.0, 'visible' => false];
        }

        $clippedStart = $start->lt($rangeStart) ? $rangeStart->copy() : $start->copy();
        $clippedEnd = $end->gt($rangeEnd) ? $rangeEnd->copy() : $end->copy();

        $offsetDays = $rangeStart->diffInDays($clippedStart);
        $spanDays = $clippedStart->diffInDays($clippedEnd) + 1;

        $offset = round($offsetDays / $totalDays * 100, 4);
        $width = round($spanDays / $totalDays * 100, 4);

        // Guard against floating drift pushing a bar past the right edge.
        if ($offset + $width > 100) {
            $width = round(100 - $offset, 4);
        }

        return ['offset' => $offset, 'width' => max($width, 0.0), 'visible' => true];
    }

    /**
     * Position a window relative to a parent window (used for phase segments
     * drawn inside their release bar). Percentages are of the parent span.
     *
     * @return array{offset: float, width: float}
     */
    public static function relativeSegment(Carbon $start, Carbon $end, Carbon $parentStart, Carbon $parentEnd): array
    {
        $seg = self::segment($start, $end, $parentStart, $parentEnd);

        return ['offset' => $seg['offset'], 'width' => $seg['width']];
    }

    /**
     * Build month column headers spanning [rangeStart, rangeEnd] for the axis.
     *
     * @return array<int, array{label: string, offset: float, width: float}>
     */
    public static function monthColumns(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $columns = [];
        $cursor = $rangeStart->copy()->startOfMonth();

        while ($cursor->lte($rangeEnd)) {
            $monthStart = $cursor->copy();
            $monthEnd = $cursor->copy()->endOfMonth();

            $seg = self::segment($monthStart, $monthEnd, $rangeStart, $rangeEnd);
            $columns[] = [
                'label' => $cursor->format('M Y'),
                'offset' => $seg['offset'],
                'width' => $seg['width'],
            ];

            $cursor->addMonth();
        }

        return $columns;
    }
}

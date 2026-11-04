<?php

namespace App\Support;

/** Picks a round top for a chart's axis, close enough above the data not to waste space. */
class ChartScale
{
    private const STEPS = [1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10];

    public static function top(float $peak): int
    {
        if ($peak <= 1) {
            return 1;
        }

        // Rounding guards against log10() landing a hair off a power of ten.
        $magnitude = 10 ** floor(round(log10($peak), 9));

        foreach (self::STEPS as $step) {
            if ($step * $magnitude >= $peak && floor($step * $magnitude) === $step * $magnitude) {
                return (int) ($step * $magnitude);
            }
        }

        return (int) (10 * $magnitude);
    }
}

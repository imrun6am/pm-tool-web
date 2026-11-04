<?php

namespace App\Support;

/**
 * The two pieces of arithmetic every analytics surface shares, so a rate is
 * null-guarded and a change is computed the same way everywhere.
 */
class Metric
{
    /** A whole percentage, or null when there is nothing to divide by. */
    public static function pct(int $part, int $whole): ?int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : null;
    }

    /**
     * A figure with how much data stands behind it. Below the minimum it isn't
     * fit to quote: callers say so rather than present it as a score.
     *
     * @return array{value: int|float|null, sample: int, enough: bool}
     */
    public static function measure(int|float|null $value, int $sample, int $minimum): array
    {
        return ['value' => $value, 'sample' => $sample, 'enough' => $value !== null && $sample >= $minimum];
    }

    /**
     * A value beside its comparison. `change` is null when either side is.
     *
     * @return array{current: int|float|null, previous: int|float|null, change: int|float|null}
     */
    public static function compare(int|float|null $current, int|float|null $previous): array
    {
        $change = null;
        if ($current !== null && $previous !== null) {
            $change = is_int($current) && is_int($previous) ? $current - $previous : round($current - $previous, 1);
        }

        return ['current' => $current, 'previous' => $previous, 'change' => $change];
    }
}

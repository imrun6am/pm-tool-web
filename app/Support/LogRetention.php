<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * How long logs are kept (LOG_RETENTION_MONTHS). Everything that removes logs,
 * or reports on them, takes its cutoff from here.
 */
class LogRetention
{
    /** Never under a month, so a blank or mistyped setting can't empty a table. */
    public static function months(): int
    {
        return max(1, (int) config('logging.retention_months', 3));
    }

    /** Logs older than this are removed. A whole day, so a chart's first day is never cut in half. */
    public static function cutoff(): Carbon
    {
        return now()->subMonthsNoOverflow(self::months())->startOfDay();
    }

    /** The cutoff as hours ago, rounded up so nothing newer than it is removed. */
    public static function hours(): int
    {
        return (int) ceil(self::cutoff()->diffInHours(now()));
    }
}

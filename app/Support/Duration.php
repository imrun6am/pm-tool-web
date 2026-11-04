<?php

namespace App\Support;

/**
 * Time as people type and read it. Stored and sent to API clients as minutes.
 */
class Duration
{
    /**
     * Minutes from "1h 30m", "2h", "45m", "1:30", or a bare number of hours
     * ("1.5"). Null when it can't be read.
     */
    public static function parse(?string $input): ?int
    {
        $text = strtolower(trim((string) $input));

        $minutes = match (true) {
            (bool) preg_match('/^(\d+(?:\.\d+)?)\s*h(?:ours?|rs?)?(?:\s*(\d+)\s*m(?:ins?|inutes?)?)?$/', $text, $m) => (float) $m[1] * 60 + (int) ($m[2] ?? 0),
            (bool) preg_match('/^(\d+)\s*m(?:ins?|inutes?)?$/', $text, $m) => (int) $m[1],
            (bool) preg_match('/^(\d+):([0-5]\d)$/', $text, $m) => (int) $m[1] * 60 + (int) $m[2],
            (bool) preg_match('/^\d+(?:\.\d+)?$/', $text) => (float) $text * 60,
            default => null,
        };

        return $minutes === null ? null : (int) round($minutes);
    }

    /** "1h 30m", "2h", "45m", or "0m". */
    public static function format(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? $hours.'h ' : '').($rest || ! $hours ? $rest.'m' : ''));
    }
}

<?php

namespace App\Support;

use App\Models\PerformanceScore;

/**
 * One canonical 1–5 score → colour scale, reused across every performance
 * surface (chips, bars, rings, radar) so the whole feature reads as one system.
 * A null score is "not yet rated" and renders neutral slate.
 */
class ScoreColor
{
    /** Solid hex for bars, rings, and SVG fills. */
    public static function hex(?float $value): string
    {
        return match (true) {
            $value === null => '#cbd5e1', // slate-300
            $value >= 4.25 => '#059669',  // emerald-600
            $value >= 3.5 => '#10b981',   // emerald-500
            $value >= 2.75 => '#f59e0b',  // amber-500
            $value >= 2.0 => '#f97316',   // orange-500
            default => '#e11d48',         // rose-600
        };
    }

    /** Tone token matching the app's stat-tile / badge palette. */
    public static function tone(?float $value): string
    {
        return match (true) {
            $value === null => 'slate',
            $value >= 3.5 => 'emerald',
            $value >= 2.75 => 'amber',
            default => 'rose',
        };
    }

    /** Tailwind pill classes (literal so they survive Tailwind's purge). */
    public static function pill(?float $value): string
    {
        return match (self::tone($value)) {
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            default => 'bg-slate-100 text-slate-500 ring-slate-500/20',
        };
    }

    /** Nearest anchor label for a (possibly fractional) score. */
    public static function label(?float $value): string
    {
        if ($value === null) {
            return 'Not rated';
        }

        return PerformanceScore::label((int) round($value));
    }

    /** Format a score out of 5, or an em dash when null. */
    public static function fmt(?float $value): string
    {
        return $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.');
    }

    /** Score as a percentage of 5 (null-safe). */
    public static function pct(?float $value): ?int
    {
        return $value === null ? null : (int) round($value / 5 * 100);
    }
}

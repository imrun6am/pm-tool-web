<?php

namespace App\Support;

/**
 * The one semantic color palette for icons and accents.
 *
 * Three layers:
 *  - TONES: a hue's ready-made classes (icon text, soft tile, solid tile, ring,
 *    hover tint for icon buttons, and a faint backdrop layer),
 *    written out in full so Tailwind keeps them (app/Support is scanned).
 *  - ALIASES: meaning → hue — success, warning, danger, info, neutral.
 *  - DOMAINS: product area → hue, so Releases (or Calendar, or Notes…) wear the
 *    same color in the sidebar, ⌘K, page headers and empty states.
 *
 * Components take a `tone` that may be a hue, an alias or a domain; anything
 * unknown falls back to neutral rather than rendering uncolored.
 */
class Palette
{
    /** @var array<string, array{text: string, soft: string, solid: string, ring: string, hover: string, back: string}> */
    public const TONES = [
        'brand' => ['text' => 'text-brand-600', 'soft' => 'bg-brand-50 text-brand-600', 'solid' => 'bg-brand-600 text-white', 'ring' => 'ring-brand-100', 'hover' => 'hover:bg-brand-50 hover:text-brand-600', 'back' => 'bg-brand-100/70'],
        'violet' => ['text' => 'text-violet-600', 'soft' => 'bg-violet-50 text-violet-600', 'solid' => 'bg-violet-600 text-white', 'ring' => 'ring-violet-100', 'hover' => 'hover:bg-violet-50 hover:text-violet-600', 'back' => 'bg-violet-100/70'],
        'purple' => ['text' => 'text-purple-600', 'soft' => 'bg-purple-50 text-purple-600', 'solid' => 'bg-purple-600 text-white', 'ring' => 'ring-purple-100', 'hover' => 'hover:bg-purple-50 hover:text-purple-600', 'back' => 'bg-purple-100/70'],
        'indigo' => ['text' => 'text-indigo-600', 'soft' => 'bg-indigo-50 text-indigo-600', 'solid' => 'bg-indigo-600 text-white', 'ring' => 'ring-indigo-100', 'hover' => 'hover:bg-indigo-50 hover:text-indigo-600', 'back' => 'bg-indigo-100/70'],
        'sky' => ['text' => 'text-sky-600', 'soft' => 'bg-sky-50 text-sky-600', 'solid' => 'bg-sky-600 text-white', 'ring' => 'ring-sky-100', 'hover' => 'hover:bg-sky-50 hover:text-sky-600', 'back' => 'bg-sky-100/70'],
        'cyan' => ['text' => 'text-cyan-600', 'soft' => 'bg-cyan-50 text-cyan-700', 'solid' => 'bg-cyan-600 text-white', 'ring' => 'ring-cyan-100', 'hover' => 'hover:bg-cyan-50 hover:text-cyan-600', 'back' => 'bg-cyan-100/70'],
        'teal' => ['text' => 'text-teal-600', 'soft' => 'bg-teal-50 text-teal-700', 'solid' => 'bg-teal-600 text-white', 'ring' => 'ring-teal-100', 'hover' => 'hover:bg-teal-50 hover:text-teal-600', 'back' => 'bg-teal-100/70'],
        'emerald' => ['text' => 'text-emerald-600', 'soft' => 'bg-emerald-50 text-emerald-600', 'solid' => 'bg-emerald-600 text-white', 'ring' => 'ring-emerald-100', 'hover' => 'hover:bg-emerald-50 hover:text-emerald-600', 'back' => 'bg-emerald-100/70'],
        'amber' => ['text' => 'text-amber-600', 'soft' => 'bg-amber-50 text-amber-700', 'solid' => 'bg-amber-500 text-white', 'ring' => 'ring-amber-100', 'hover' => 'hover:bg-amber-50 hover:text-amber-600', 'back' => 'bg-amber-100/70'],
        'orange' => ['text' => 'text-orange-600', 'soft' => 'bg-orange-50 text-orange-600', 'solid' => 'bg-orange-500 text-white', 'ring' => 'ring-orange-100', 'hover' => 'hover:bg-orange-50 hover:text-orange-600', 'back' => 'bg-orange-100/70'],
        'rose' => ['text' => 'text-rose-600', 'soft' => 'bg-rose-50 text-rose-600', 'solid' => 'bg-rose-600 text-white', 'ring' => 'ring-rose-100', 'hover' => 'hover:bg-rose-50 hover:text-rose-600', 'back' => 'bg-rose-100/70'],
        'pink' => ['text' => 'text-pink-600', 'soft' => 'bg-pink-50 text-pink-600', 'solid' => 'bg-pink-600 text-white', 'ring' => 'ring-pink-100', 'hover' => 'hover:bg-pink-50 hover:text-pink-600', 'back' => 'bg-pink-100/70'],
        'fuchsia' => ['text' => 'text-fuchsia-600', 'soft' => 'bg-fuchsia-50 text-fuchsia-600', 'solid' => 'bg-fuchsia-600 text-white', 'ring' => 'ring-fuchsia-100', 'hover' => 'hover:bg-fuchsia-50 hover:text-fuchsia-600', 'back' => 'bg-fuchsia-100/70'],
        'slate' => ['text' => 'text-slate-500', 'soft' => 'bg-slate-100 text-slate-600', 'solid' => 'bg-slate-700 text-white', 'ring' => 'ring-slate-200', 'hover' => 'hover:bg-slate-100 hover:text-slate-700', 'back' => 'bg-slate-200/70'],
    ];

    /** What a color means. */
    public const ALIASES = [
        'success' => 'emerald',
        'warning' => 'amber',
        'danger' => 'rose',
        'info' => 'brand',
        'neutral' => 'slate',
    ];

    /** Which hue each product area wears everywhere. */
    public const DOMAINS = [
        'dashboard' => 'brand',
        'ask' => 'purple',
        'releases' => 'violet',
        'projects' => 'amber',
        'teams' => 'pink',
        'board' => 'sky',
        'tasks' => 'sky',
        'tasksheet' => 'emerald',
        'calendar' => 'rose',
        'notes' => 'orange',
        'meetings' => 'teal',
        'activity' => 'cyan',
        'performance' => 'fuchsia',
        'kpi-reports' => 'indigo',
        'users' => 'slate',
        'notifications' => 'brand',
        'ai' => 'purple',
        'ai-usage' => 'purple',
    ];

    /** Resolve a hue, alias or domain to a hue name (neutral when unknown). */
    public static function hue(?string $tone): string
    {
        $tone = self::ALIASES[$tone] ?? self::DOMAINS[$tone] ?? $tone;

        return array_key_exists((string) $tone, self::TONES) ? $tone : self::ALIASES['neutral'];
    }

    /** Icon/text color, e.g. "text-rose-600". */
    public static function text(?string $tone): string
    {
        return self::TONES[self::hue($tone)]['text'];
    }

    /** Tinted tile: light background + colored icon. */
    public static function soft(?string $tone): string
    {
        return self::TONES[self::hue($tone)]['soft'];
    }

    /** Filled tile: strong background + white icon. */
    public static function solid(?string $tone): string
    {
        return self::TONES[self::hue($tone)]['solid'];
    }

    public static function ring(?string $tone): string
    {
        return self::TONES[self::hue($tone)]['ring'];
    }

    /** Hover tint for an icon button whose action has this meaning. */
    public static function hover(?string $tone): string
    {
        return self::TONES[self::hue($tone)]['hover'];
    }

    /** Faint offset layer behind an illustration tile. */
    public static function back(?string $tone): string
    {
        return self::TONES[self::hue($tone)]['back'];
    }
}

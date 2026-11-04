<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * Which days people work: `planning.weekend_days` every week, plus an optional
 * `planning.alternate_off_day` every second week. Everything that asks
 * "is this a working day?" asks here, so the app agrees with itself. Weeks in
 * reports still run Monday to Sunday: that's a bucket, not a working pattern.
 */
class WorkingWeek
{
    private const ALTERNATE_CYCLE_DAYS = 14;

    /** In the order JavaScript's getDay() counts them. */
    private const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /** @return list<string> lowercase English day names */
    public static function weekendDays(): array
    {
        $days = array_values(array_intersect(self::DAYS, (array) config('planning.weekend_days')));

        // Seven days off would leave callers looking for a working day forever.
        return count($days) === count(self::DAYS) ? [] : $days;
    }

    public static function isWeekend(CarbonInterface $day): bool
    {
        return in_array(strtolower($day->englishDayOfWeek), self::weekendDays(), true)
            || self::isAlternateDayOff($day);
    }

    /** A date that's off, with the same weekday off every second week either side of it. */
    public static function alternateOffDay(): ?Carbon
    {
        $date = config('planning.alternate_off_day');

        if (blank($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (InvalidFormatException) {
            return null; // A typo in a setting shouldn't take every page down.
        }
    }

    public static function isAlternateDayOff(CarbonInterface $day): bool
    {
        $anchor = self::alternateOffDay();

        return $anchor !== null && (int) round(abs($anchor->diffInDays($day->copy()->startOfDay()))) % self::ALTERNATE_CYCLE_DAYS === 0;
    }

    /**
     * The alternate days off in a period, as Y-m-d.
     *
     * @return list<string>
     */
    public static function alternateDaysOffBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        for ($day = Carbon::parse($from)->startOfDay(); $day->lte($to); $day->addDay()) {
            if (self::isAlternateDayOff($day)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    public static function isWorkingDay(CarbonInterface $day): bool
    {
        return ! self::isWeekend($day);
    }

    /** The working day before a date, today by default. */
    public static function previous(?CarbonInterface $from = null): Carbon
    {
        $day = Carbon::parse($from ?? today());

        do {
            $day->subDay();
        } while (self::isWeekend($day));

        return $day;
    }

    /** The working day after a date. */
    public static function next(CarbonInterface $from): Carbon
    {
        $day = Carbon::parse($from);

        do {
            $day->addDay();
        } while (self::isWeekend($day));

        return $day;
    }

    /** @return list<int> weekend days as JavaScript's getDay() numbers them (Sunday is 0) */
    public static function weekendDayNumbers(): array
    {
        return array_keys(array_intersect(self::DAYS, self::weekendDays()));
    }

    /** "Friday", or "Friday and every second Saturday". Empty with no weekend. */
    public static function weekendLabel(): string
    {
        $parts = collect(self::weekendDays())->map(fn (string $day) => ucfirst($day));

        if ($anchor = self::alternateOffDay()) {
            $parts->push('every second '.$anchor->englishDayOfWeek);
        }

        return $parts->join(', ', ' and ');
    }
}

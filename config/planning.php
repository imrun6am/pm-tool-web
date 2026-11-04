<?php

return [
    /*
    | The weekend, used everywhere the app asks whether a day is a working day
    | (App\\Support\\WorkingWeek). Days are English names, comma separated.
    */
    'weekend_days' => array_map(
        fn (string $day) => strtolower(trim($day)),
        explode(',', (string) env('PLANNING_WEEKEND_DAYS', 'friday')),
    ),

    /*
    | A day off every second week: this date, and every 14 days either side of it.
    | 2026-08-01 is a Saturday off, so every other Saturday is worked. Empty for none.
    */
    'alternate_off_day' => env('PLANNING_ALTERNATE_OFF_DAY', '2026-08-01'),

    'hours_per_day' => (float) env('PLANNING_HOURS_PER_DAY', 8),

    // How long a request can wait before it's flagged.
    'triage_response_hours' => (int) env('PLANNING_TRIAGE_RESPONSE_HOURS', 48),

    // Weeks shown on the capacity page.
    'capacity_weeks' => 6,
];

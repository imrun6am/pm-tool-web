<?php

return [
    /*
    | The working week used for capacity. Days are English names, comma separated.
    */
    'weekend_days' => array_map(
        fn (string $day) => strtolower(trim($day)),
        explode(',', (string) env('PLANNING_WEEKEND_DAYS', 'friday')),
    ),

    'hours_per_day' => (float) env('PLANNING_HOURS_PER_DAY', 8),

    // How long a request can wait before it's flagged.
    'triage_response_hours' => (int) env('PLANNING_TRIAGE_RESPONSE_HOURS', 48),

    // Weeks shown on the capacity page.
    'capacity_weeks' => 6,
];

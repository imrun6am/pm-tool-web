<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

trait ReadsPeopleReportFilters
{
    /** Long enough for a year's review, short enough to stay quick. */
    private const MAX_PERIOD_DAYS = 366;

    /**
     * The period defaults to the last 30 days, and never reaches past today:
     * work hasn't happened yet, and days to come would read as days missed.
     *
     * @return array{from: Carbon, to: Carbon, team_id: ?int}
     */
    protected function peopleReportFilters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => array_filter(['nullable', 'date', $request->filled('from') ? 'after_or_equal:from' : null]),
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);

        $to = filled($data['to'] ?? null) ? Carbon::parse($data['to'])->startOfDay()->min(today()) : today();
        $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : $to->copy()->subDays(29);

        if ($from->diffInDays($to) >= self::MAX_PERIOD_DAYS) {
            throw ValidationException::withMessages(['from' => 'Choose a period of a year or less.']);
        }

        return [
            'from' => $from,
            'to' => $to,
            'team_id' => isset($data['team_id']) ? (int) $data['team_id'] : null,
        ];
    }
}

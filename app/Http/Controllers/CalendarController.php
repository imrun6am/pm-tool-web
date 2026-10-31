<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function index(Request $request): View
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $month = min(max((int) $request->integer('month', (int) now()->month), 1), 12);

        $first = Carbon::create($year, $month, 1)->startOfDay();
        [$gridStart, $gridEnd] = $this->events->monthWindow($year, $month);

        $byDate = $this->events->groupByDate(
            $this->events->inWindow($gridStart, $gridEnd),
            $gridStart,
            $gridEnd,
        );

        return view('calendar.index', [
            'weeks' => $this->buildWeeks($gridStart, $gridEnd, $month, $byDate),
            'monthLabel' => $first->format('F Y'),
            'prev' => $first->copy()->subMonthNoOverflow(),
            'next' => $first->copy()->addMonthNoOverflow(),
            'today' => now(),
        ]);
    }

    /**
     * Lay the window out as weeks of seven days — a view concern, so it stays
     * here rather than in the service the API also uses.
     *
     * @param  array<string, list<Event>>  $byDate
     * @return list<list<array<string, mixed>>>
     */
    private function buildWeeks(Carbon $gridStart, Carbon $gridEnd, int $month, array $byDate): array
    {
        $weeks = [];
        $cursor = $gridStart->copy();
        $today = now()->toDateString();

        while ($cursor->lte($gridEnd)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $date = $cursor->toDateString();

                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => (int) $cursor->month === $month,
                    'isToday' => $date === $today,
                    'events' => $byDate[$date] ?? [],
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }
}

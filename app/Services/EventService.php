<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calendar events.
 *
 * The one rule worth centralising is what "all day" means: whatever times the
 * client sent, an all-day event is normalized to the day's own bounds, so a
 * meeting marked all-day spans the same window however it was entered.
 *
 * The month window is the *grid* a client draws — the whole weeks spanning the
 * month, not just its first-to-last day — so events spilling into the leading
 * and trailing days are present rather than appearing to vanish at the edges.
 */
class EventService
{
    /** The attributes an event write accepts from a request. */
    private const WRITABLE = [
        'title', 'description', 'type', 'starts_at', 'ends_at', 'all_day', 'location', 'release_id',
    ];

    /**
     * Events overlapping a window, with their relations loaded.
     *
     * @param  array{release_id?: ?int, type?: ?string}  $filters
     * @return Collection<int, Event>
     */
    public function inWindow(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        return Event::query()
            ->with(['release', 'creator'])
            ->whereDate('starts_at', '<=', $to->toDateString())
            ->where(fn ($q) => $q
                ->whereDate('ends_at', '>=', $from->toDateString())
                ->orWhere(fn ($q2) => $q2
                    ->whereNull('ends_at')
                    ->whereDate('starts_at', '>=', $from->toDateString())))
            ->when($filters['release_id'] ?? null, fn ($q, $id) => $q->where('release_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The full weeks spanning a month — the grid a calendar renders.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function monthWindow(int $year, int $month): array
    {
        $first = Carbon::create($year, min(max($month, 1), 12), 1)->startOfDay();

        return [
            $first->copy()->startOfWeek(Carbon::SUNDAY),
            $first->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY),
        ];
    }

    /**
     * Y-m-d => events covering that day, clipped to the window. Lets a caller
     * fill a month grid without re-deriving which days a multi-day event spans.
     *
     * @param  Collection<int, Event>  $events
     * @return array<string, list<Event>>
     */
    public function groupByDate(Collection $events, Carbon $from, Carbon $to): array
    {
        $byDate = [];

        foreach ($events as $event) {
            foreach ($event->coveredDates($from, $to) as $date) {
                $byDate[$date][] = $event;
            }
        }

        return $byDate;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $attendeeIds
     */
    public function create(array $attributes, array $attendeeIds, User $creator): Event
    {
        $event = Event::create($this->attributes($attributes) + ['created_by' => $creator->id]);
        $event->attendees()->sync($attendeeIds);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $attendeeIds
     */
    public function update(Event $event, array $attributes, array $attendeeIds): Event
    {
        $event->update($this->attributes($attributes));
        $event->attendees()->sync($attendeeIds);

        return $event;
    }

    /**
     * Writable attributes with an all-day event snapped to day bounds.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function attributes(array $attributes): array
    {
        $data = array_intersect_key($attributes, array_flip(self::WRITABLE));

        $start = Carbon::parse($data['starts_at']);
        $end = ! empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;

        if (! empty($data['all_day'])) {
            $start = $start->startOfDay();
            $end = ($end ?? $start)->endOfDay();
        }

        $data['starts_at'] = $start;
        $data['ends_at'] = $end;

        return $data;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\EventRequest;
use App\Http\Resources\V1\EventResource;
use App\Http\Resources\V1\MeetingNoteResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Calendar events. Any signed-in user creates; EventPolicy limits editing and
 * deleting to the creator or a lead.
 *
 * The all-day normalization and the month-grid window live in EventService,
 * shared with the Blade calendar — so a client drawing a month sees exactly the
 * events the web calendar shows for the same month.
 */
class EventController extends ApiController
{
    public function __construct(private readonly EventService $events) {}

    /**
     * Events overlapping a window.
     *
     * The default window is the month *grid* — the whole weeks spanning the
     * month — so events spilling into the leading and trailing days are
     * present. An explicit `from`/`to` overrides it.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        [$from, $to] = $this->window($request);

        $events = $this->events->inWindow($from, $to, [
            'release_id' => $this->filterId($request, 'release_id'),
            'type' => $request->input('type'),
        ]);

        // Y-m-d => [event ids], so a client can fill a month grid without
        // re-deriving which days each multi-day event covers.
        $byDate = array_map(
            fn (array $dayEvents) => array_map(fn (Event $e) => $e->id, $dayEvents),
            $this->events->groupByDate($events, $from, $to),
        );

        return $this->ok([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => EventResource::collection($events->load('attendees'))->resolve($request),
            'events_by_date' => $byDate,
        ]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $event->load(['creator', 'release', 'attendees']);

        // Attendees-only meeting notes are filtered out for viewers who may
        // not read them.
        $notes = $event->meetingNotes()->with('author')->visibleTo($request->user())->get();

        return $this->ok(
            (new EventResource($event))->additional([
                'meeting_notes' => MeetingNoteResource::collection($notes)->resolve($request),
            ])
        );
    }

    public function store(EventRequest $request): JsonResponse
    {
        $event = $this->events->create(
            $request->validated(),
            $request->validated('attendees') ?? [],
            $request->user(),
        );

        return $this->created(
            new EventResource($event->load(['creator', 'release', 'attendees'])),
            'Event created.'
        );
    }

    public function update(EventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $this->events->update($event, $request->validated(), $request->validated('attendees') ?? []);

        return $this->ok(
            new EventResource($event->load(['creator', 'release', 'attendees'])),
            'Event updated.'
        );
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return $this->message('Event deleted.');
    }

    /**
     * The query window: an explicit from/to, or the grid of the requested
     * (default current) month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Request $request): array
    {
        if ($request->filled('from') || $request->filled('to')) {
            $from = Carbon::parse($request->input('from', $request->input('to')))->startOfDay();
            $to = Carbon::parse($request->input('to', $request->input('from')))->endOfDay();

            return $from->gt($to) ? [$to, $from] : [$from, $to];
        }

        return $this->events->monthWindow(
            (int) $request->integer('year', (int) now()->year),
            (int) $request->integer('month', (int) now()->month),
        );
    }
}

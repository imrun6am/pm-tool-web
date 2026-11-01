<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Release;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    /** Creating happens in the calendar's drawer; this URL just opens it. */
    public function create(): RedirectResponse
    {
        $date = request('date');
        $day = is_string($date) && strtotime($date) ? Carbon::parse($date) : null;

        return redirect()->route('calendar.index', array_filter([
            'year' => $day?->year,
            'month' => $day?->month,
            'new' => $day?->toDateString() ?? 1,
        ]));
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = $this->events->create(
            $request->validated(),
            $request->validated('attendees') ?? [],
            $request->user(),
        );

        // Back to the month it landed in, so the new event is right there.
        return redirect()
            ->route('calendar.index', ['year' => $event->starts_at->year, 'month' => $event->starts_at->month])
            ->with('success', 'Event created.');
    }

    public function show(Event $event): View
    {
        $event->load(['creator', 'release', 'attendees']);

        // Meeting notes the viewer may see (attendees-only ones are filtered out).
        $event->setRelation('meetingNotes', $event->meetingNotes()
            ->with('author')->visibleTo(request()->user())->get());

        // Editing happens in a drawer on this page, for those who may edit.
        $form = request()->user()->can('update', $event) ? [
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(),
            'selectedAttendees' => $event->attendees->pluck('id')->all(),
            'openEdit' => request()->boolean('edit'),
        ] : [];

        return view('events.show', ['event' => $event, ...$form]);
    }

    /** Editing happens in the event page's drawer; this URL just opens it. */
    public function edit(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        return redirect()->route('events.show', [$event, 'edit' => 1]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $this->events->update($event, $request->validated(), $request->validated('attendees') ?? []);

        return redirect()->route('events.show', $event)->with('success', 'Event updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return redirect()->route('calendar.index')->with('success', 'Event deleted.');
    }
}

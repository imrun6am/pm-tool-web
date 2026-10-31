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

    public function create(): View
    {
        $date = request('date');
        $start = $date ? Carbon::parse($date)->setTime(9, 0) : now()->addHour()->startOfHour();

        return view('events.create', [
            'event' => new Event(['type' => 'meeting', 'starts_at' => $start]),
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(),
            'selectedAttendees' => [],
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = $this->events->create(
            $request->validated(),
            $request->validated('attendees') ?? [],
            $request->user(),
        );

        return redirect()->route('events.show', $event)->with('success', 'Event created.');
    }

    public function show(Event $event): View
    {
        $event->load(['creator', 'release', 'attendees']);

        // Meeting notes the viewer may see (attendees-only ones are filtered out).
        $event->setRelation('meetingNotes', $event->meetingNotes()
            ->with('author')->visibleTo(request()->user())->get());

        return view('events.show', compact('event'));
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);

        return view('events.edit', [
            'event' => $event,
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(),
            'selectedAttendees' => $event->attendees->pluck('id')->all(),
        ]);
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

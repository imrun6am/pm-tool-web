<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeetingNoteRequest;
use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Release;
use App\Models\User;
use App\Services\MeetingNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MeetingNoteController extends Controller
{
    public function __construct(private readonly MeetingNoteService $meetingNotes) {}

    public function index(): View
    {
        $filter = request('release'); // null (all) | 'general' | release id
        $range = $this->meetingNotes->normalizeRange(request()->only(['from', 'to']));
        $type = request()->filled('type') ? request('type') : null;
        $author = request()->filled('author') ? request()->integer('author') : null;
        $attendee = request()->filled('attendee') ? request()->integer('attendee') : null;
        $search = request()->filled('search') ? trim((string) request('search')) : null;

        return view('meeting-notes.index', [
            'notes' => $this->meetingNotes
                ->visibleTo(request()->user(), [
                    'release' => $filter,
                    'type' => $type,
                    'author' => $author,
                    'attendee' => $attendee,
                    'search' => $search,
                    ...$range,
                ])
                ->paginate(24)
                ->withQueryString(),
            'filter' => $filter,
            'type' => $type,
            'author' => $author,
            'attendee' => $attendee,
            'search' => $search,
            'from' => $range['from'],
            'to' => $range['to'],
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            // Person filters list everyone who could plausibly appear, including
            // deactivated users, so an old note stays reachable by its author.
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        // "Write meeting note" on a meeting-type event passes ?event={id}:
        // the note is pre-filled from — and linked to — that event.
        $event = ($eventId = request()->integer('event')) ? Event::find($eventId) : null;

        return view('meeting-notes.create', [
            'meetingNote' => new MeetingNote([
                'event_id' => $event?->id,
                'title' => $event?->title,
                'release_id' => $event?->release_id ?? (request()->integer('release') ?: null),
                'meeting_date' => $event?->starts_at ?? now(),
            ]),
            'releases' => Release::ongoing()->orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(),
            // Notes written from an event start with that event's attendees.
            'selectedAttendees' => $event ? $event->attendees()->pluck('users.id')->all() : [],
        ]);
    }

    public function store(MeetingNoteRequest $request): RedirectResponse
    {
        $note = $this->meetingNotes->create(
            $request->validated(),
            $request->validated('attendees') ?? [],
            $request->user(),
        );

        return redirect()->route('meeting-notes.show', $note)->with('success', 'Meeting note created.');
    }

    public function show(MeetingNote $meetingNote): View
    {
        $this->authorize('view', $meetingNote);

        $meetingNote->load(['author', 'release', 'event', 'attendees']);

        return view('meeting-notes.show', compact('meetingNote'));
    }

    public function edit(MeetingNote $meetingNote): View
    {
        $this->authorize('update', $meetingNote);

        return view('meeting-notes.edit', [
            'meetingNote' => $meetingNote,
            // Ongoing releases, plus this note's own even if it has since
            // completed — so editing never silently drops an existing link.
            'releases' => $this->meetingNotes->linkableReleases($meetingNote),
            'users' => User::active()->orderBy('name')->get(),
            'selectedAttendees' => $meetingNote->attendees()->pluck('users.id')->all(),
        ]);
    }

    public function update(MeetingNoteRequest $request, MeetingNote $meetingNote): RedirectResponse
    {
        $this->authorize('update', $meetingNote);

        $this->meetingNotes->update(
            $meetingNote,
            $request->validated(),
            $request->validated('attendees') ?? [],
        );

        return redirect()->route('meeting-notes.show', $meetingNote)
            ->with('success', 'Meeting note updated.');
    }

    public function destroy(MeetingNote $meetingNote): RedirectResponse
    {
        $this->authorize('delete', $meetingNote);

        $meetingNote->delete();

        return redirect()->route('meeting-notes.index')->with('success', 'Meeting note deleted.');
    }
}

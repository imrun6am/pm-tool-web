<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\MeetingNoteRequest;
use App\Http\Resources\V1\MeetingNoteResource;
use App\Models\MeetingNote;
use App\Services\MeetingNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Meeting minutes — release-linked or general.
 *
 * Filtering and the attendee sync live in MeetingNoteService, shared with the
 * Blade pages. Unlike personal notes these are shared team records: a lead may
 * delete any of them, while editing stays with the author.
 */
class MeetingNoteController extends ApiController
{
    public function __construct(private readonly MeetingNoteService $meetingNotes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'type' => ['nullable', 'string'],
            'author' => ['nullable', 'integer'],
            'attendee' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $range = $this->meetingNotes->normalizeRange($request->only(['from', 'to']));

        $query = $this->meetingNotes->visibleTo($request->user(), [
            'release' => $request->input('release'), // null | 'general' | release id
            'type' => $request->input('type'),
            'author' => $this->filterId($request, 'author'),
            'attendee' => $this->filterId($request, 'attendee'),
            'search' => $request->input('search'),
            ...$range,
        ]);

        return $this->paginate($request, $query, MeetingNoteResource::class);
    }

    public function show(MeetingNote $meetingNote): JsonResponse
    {
        $this->authorize('view', $meetingNote);

        return $this->ok(
            new MeetingNoteResource($meetingNote->load(['author', 'release', 'event', 'attendees']))
        );
    }

    public function store(MeetingNoteRequest $request): JsonResponse
    {
        $note = $this->meetingNotes->create(
            $request->validated(),
            $request->validated('attendees') ?? [],
            $request->user(),
        );

        return $this->created(
            new MeetingNoteResource($note->load(['author', 'release', 'event', 'attendees'])),
            'Meeting note created.'
        );
    }

    public function update(MeetingNoteRequest $request, MeetingNote $meetingNote): JsonResponse
    {
        $this->authorize('update', $meetingNote);

        $this->meetingNotes->update(
            $meetingNote,
            $request->validated(),
            $request->validated('attendees') ?? [],
        );

        return $this->ok(
            new MeetingNoteResource($meetingNote->load(['author', 'release', 'event', 'attendees'])),
            'Meeting note updated.'
        );
    }

    public function destroy(MeetingNote $meetingNote): JsonResponse
    {
        $this->authorize('delete', $meetingNote);

        $meetingNote->delete();

        return $this->message('Meeting note deleted.');
    }
}

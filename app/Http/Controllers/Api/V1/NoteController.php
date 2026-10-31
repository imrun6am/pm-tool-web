<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\NoteRequest;
use App\Http\Resources\V1\NoteResource;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Personal daily notes.
 *
 * The visibility scoping and recipient rules live in NoteService, shared with
 * the Blade notes page. Notes are personal even from leads: NotePolicy lets only
 * the author edit or delete one.
 */
class NoteController extends ApiController
{
    public function __construct(private readonly NoteService $notes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            // No `exists` rule: an unknown id returns an empty page rather than
            // an error that confirms whether that user exists.
            'author' => ['nullable', 'integer'],
        ]);

        $filters = $this->notes->normalizeFilters($request->only(['date', 'from', 'to']))
            + ['author' => $this->filterId($request, 'author')];

        return $this->paginate(
            $request,
            $this->notes->visibleTo($request->user(), $filters),
            NoteResource::class,
        );
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->isVisibleTo($request->user()), 404);

        return $this->ok(new NoteResource($note->load(['author', 'recipients'])));
    }

    public function store(NoteRequest $request): JsonResponse
    {
        $note = $this->notes->create(
            $request->validated(),
            $request->validated('recipients') ?? [],
            $request->user(),
        );

        return $this->created(new NoteResource($note->load(['author', 'recipients'])), 'Note added.');
    }

    public function update(NoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $this->notes->update($note, $request->validated(), $request->validated('recipients') ?? []);

        return $this->ok(new NoteResource($note->load(['author', 'recipients'])), 'Note updated.');
    }

    public function destroy(Note $note): JsonResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        return $this->message('Note deleted.');
    }
}

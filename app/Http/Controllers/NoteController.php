<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Models\User;
use App\Services\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function __construct(private readonly NoteService $notes) {}

    public function index(Request $request): View
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            // Integer only, deliberately no `exists` rule: an unknown id should
            // return an empty list, not an error confirming who exists.
            'author' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $author = $request->filled('author') ? $request->integer('author') : null;
        $filters = $this->notes->normalizeFilters($request->only(['date', 'from', 'to'])) + ['author' => $author];

        return view('notes.index', [
            'notes' => $this->notes->visibleTo($user, $filters)->paginate(15)->withQueryString(),
            'date' => $filters['date'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'author' => $author,
            'today' => now()->toDateString(),
            // Two different lists on purpose: `users` is the recipient picker's
            // (everyone but you), `authors` is who you could filter by.
            'users' => User::active()->where('id', '!=', $user->id)->orderBy('name')->get(),
            'authors' => $this->notes->authorsVisibleTo($user),
        ]);
    }

    public function store(NoteRequest $request): RedirectResponse
    {
        $this->notes->create(
            $request->validated(),
            $request->validated('recipients') ?? [],
            $request->user(),
        );

        return redirect()->route('notes.index')->with('success', 'Note added.');
    }

    public function update(NoteRequest $request, Note $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $this->notes->update($note, $request->validated(), $request->validated('recipients') ?? []);

        return back()->with('success', 'Note updated.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }
}

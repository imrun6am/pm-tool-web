<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use App\Services\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function __construct(private readonly BoardService $board) {}

    public function index(Request $request): View
    {
        $releaseId = $request->filled('release_id') ? (int) $request->integer('release_id') : null;
        $assigneeId = $request->filled('assignee_id') ? (int) $request->integer('assignee_id') : null;

        return view('board.index', [
            'columns' => $this->board->columns([
                'release_id' => $releaseId,
                'assignee_id' => $assigneeId,
            ]),
            'statuses' => Task::STATUSES,
            'release' => $releaseId ? Release::find($releaseId) : null,
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'filters' => ['release_id' => $releaseId, 'assignee_id' => $assigneeId],
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'release_id' => ['required', Rule::exists('releases', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $this->board->quickAdd(
            Release::findOrFail($data['release_id']),
            $data['title'],
            $data['status'],
            $request->user(),
        );

        return back()->with('success', 'Task added to the board.');
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'ordered_ids' => ['array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $this->board->move($task, $data['status'], $data['ordered_ids'] ?? []);

        return response()->json(['ok' => true]);
    }
}

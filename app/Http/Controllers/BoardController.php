<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsTaskFilters;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoardController extends Controller
{
    use ReadsTaskFilters;

    public function __construct(private readonly BoardService $board) {}

    public function index(Request $request): View
    {
        $releaseId = $this->idOrNoneFilter($request, 'release_id');
        $teamId = $this->idOrNoneFilter($request, 'team_id');
        $assigneeId = $request->filled('assignee_id') ? (int) $request->integer('assignee_id') : null;

        return view('board.index', [
            'columns' => $this->board->columns([
                'release_id' => $releaseId,
                'team_id' => $teamId,
                'assignee_id' => $assigneeId,
            ]),
            'statuses' => Task::STATUSES,
            'release' => is_int($releaseId) ? Release::find($releaseId) : null,
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            'teams' => Team::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'filters' => ['release_id' => $releaseId, 'team_id' => $teamId, 'assignee_id' => $assigneeId],
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'release_id' => ['nullable', Rule::exists('releases', 'id')],
            'team_id' => ['nullable', Rule::exists('teams', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $this->board->quickAdd(
            isset($data['release_id']) ? Release::findOrFail($data['release_id']) : null,
            $data['title'],
            $data['status'],
            $request->user(),
            isset($data['team_id']) ? (int) $data['team_id'] : null,
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

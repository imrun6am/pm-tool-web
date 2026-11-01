<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsTaskFilters;
use App\Http\Requests\BoardTaskRequest;
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
        $viewer = $request->user();

        // Developers and QA only ever see their own teams' work, releases and people.
        $limited = $viewer->hasLimitedAccess();
        $teamIds = $limited ? $viewer->currentTeamIds() : null;

        return view('board.index', [
            'columns' => $this->board->columns([
                'release_id' => $releaseId,
                'team_id' => $teamId,
                'assignee_id' => $assigneeId,
            ], $viewer),
            'statuses' => Task::STATUSES,
            'release' => is_int($releaseId) ? Release::find($releaseId) : null,
            'releases' => Release::visibleTo($viewer)->orderBy('year', 'desc')->orderBy('name')->get(),
            'teams' => Team::when($limited, fn ($q) => $q->whereIn('id', $teamIds))->orderBy('name')->get(),
            'users' => User::visibleTo($viewer)->orderBy('name')->get(),
            'filters' => ['release_id' => $releaseId, 'team_id' => $teamId, 'assignee_id' => $assigneeId],
        ]);
    }

    public function storeTask(BoardTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

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

        $this->authorize('update', $task);

        $this->board->move($task, $data['status'], $data['ordered_ids'] ?? [], $request->user());

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsTaskFilters;
use App\Http\Requests\AssignTaskRequest;
use App\Http\Requests\BoardTaskRequest;
use App\Http\Requests\BulkDeleteTasksRequest;
use App\Http\Requests\WipLimitRequest;
use App\Models\Label;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\BoardService;
use App\Services\SavedViewService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoardController extends Controller
{
    use ReadsTaskFilters;

    public function __construct(
        private readonly BoardService $board,
        private readonly TaskService $tasks,
        private readonly SavedViewService $savedViews,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->defaultToOwnTasks([
            'release_id' => $this->idOrNoneFilter($request, 'release_id'),
            'team_id' => $this->idOrNoneFilter($request, 'team_id'),
            'assignee_id' => $this->idOrNoneFilter($request, 'assignee_id'),
        ] + $this->fieldFilters($request), $request);
        $releaseId = $filters['release_id'];
        $teamId = $filters['team_id'];
        $viewer = $request->user();

        // Developers and QA only ever see their own teams' work, releases and people.
        $limited = $viewer->hasLimitedAccess();
        $teamIds = $limited ? $viewer->currentTeamIds() : null;

        $group = in_array($request->input('group'), BoardService::GROUPS, true) ? $request->input('group') : null;

        $columns = $this->board->columns($filters, $viewer);

        return view('board.index', [
            'columns' => $group ? array_map(fn ($cards) => $this->board->grouped($cards, $group), $columns) : $columns,
            'group' => $group,
            'wipLimits' => is_int($teamId) ? $this->board->wipLimits($teamId) : [],
            'statuses' => Task::STATUSES,
            'release' => is_int($releaseId) ? Release::find($releaseId) : null,
            'releases' => Release::visibleTo($viewer)->orderBy('year', 'desc')->orderBy('name')->get(),
            'teams' => Team::when($limited, fn ($q) => $q->whereIn('id', $teamIds))->orderBy('name')->get(),
            'users' => User::visibleTo($viewer)->orderBy('name')->get(),
            'labels' => Label::orderBy('name')->get(),
            'savedViews' => $this->savedViews->forPage($viewer, 'board'),
            'filters' => $filters,
        ]);
    }

    public function updateWipLimit(WipLimitRequest $request): RedirectResponse
    {
        $this->board->setWipLimit($request->integer('team_id'), $request->validated('status'), $request->validated('max_tasks'));

        return back()->with('success', 'Limit saved.');
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
            isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
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

    /** Who the viewer may pick for this card; the board's script fetches it when the picker opens. */
    public function assignees(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        return response()->json([
            'assignee_id' => $task->assignee_id,
            'people' => $this->tasks->cardAssignees($task, $request->user())
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->all(),
        ]);
    }

    /**
     * Answers JSON for the board's script: the re-rendered control, and whether
     * the viewer can still see the card (it may have been theirs alone).
     */
    public function assign(AssignTaskRequest $request, Task $task): JsonResponse
    {
        $this->board->assign($task, $request->validated('assignee_id'));
        $task->load('assignee');

        return response()->json([
            'ok' => true,
            'assignee_id' => $task->assignee_id,
            'assignee_name' => $task->assignee?->name,
            'visible' => $task->isVisibleTo($request->user()),
            'html' => view('partials.board-assignee', ['task' => $task])->render(),
        ]);
    }

    /** Admins only. Answers JSON for the board's script, which updates in place. */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('deleteFromBoard', $task);

        return response()->json(['ok' => true] + $this->board->deleteCards([$task]));
    }

    /** Admins only; the request checks the role, then every id. */
    public function bulkDestroy(BulkDeleteTasksRequest $request): JsonResponse
    {
        return response()->json(['ok' => true] + $this->board->deleteCards($request->tasks()));
    }
}

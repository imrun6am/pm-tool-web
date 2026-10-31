<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\MoveTaskRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Release;
use App\Models\Task;
use App\Services\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The kanban board.
 *
 * The grouping and the move-plus-reorder transaction live in BoardService,
 * shared with the Blade board. This layer adds the per-column label, color, and
 * count that a JSON client needs but Blade reads from `Task::STATUSES` itself.
 */
class BoardController extends ApiController
{
    public function __construct(private readonly BoardService $board) {}

    public function index(Request $request): JsonResponse
    {
        $releaseId = $this->filterId($request, 'release_id');
        $assigneeId = $this->filterId($request, 'assignee_id');

        $grouped = $this->board->columns([
            'release_id' => $releaseId,
            'assignee_id' => $assigneeId,
        ]);

        $columns = [];
        foreach (Task::STATUSES as $status => $label) {
            $cards = $grouped[$status];

            $columns[] = [
                'status' => $status,
                'label' => $label,
                'color' => Task::STATUS_COLORS[$status] ?? 'gray',
                'count' => $cards->count(),
                'tasks' => TaskResource::collection($cards)->resolve($request),
            ];
        }

        return $this->ok([
            'columns' => $columns,
            'filters' => [
                'release_id' => $releaseId,
                'assignee_id' => $assigneeId,
            ],
        ]);
    }

    /** Quick-add straight onto a column. */
    public function storeTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'release_id' => ['required', Rule::exists('releases', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $task = $this->board->quickAdd(
            Release::findOrFail($data['release_id']),
            $data['title'],
            $data['status'],
            $request->user(),
        );

        return $this->created(
            new TaskResource($task->load(['release.project', 'assignee'])->loadCount('comments')),
            'Task added to the board.'
        );
    }

    /** A drag: status change and column reorder in one atomic step. */
    public function move(MoveTaskRequest $request, Task $task): JsonResponse
    {
        $this->board->move(
            $task,
            $request->string('status')->toString(),
            $request->input('ordered_ids', []),
        );

        return $this->ok(
            new TaskResource($task->fresh()->load(['release.project', 'assignee'])->loadCount('comments')),
            'Card moved.'
        );
    }
}

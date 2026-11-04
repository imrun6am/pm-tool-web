<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ReadsTaskFilters;
use App\Http\Requests\Api\V1\MoveTaskRequest;
use App\Http\Requests\BoardTaskRequest;
use App\Http\Requests\BulkDeleteTasksRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Release;
use App\Models\Task;
use App\Services\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The kanban board.
 *
 * The grouping and the move-plus-reorder transaction live in BoardService,
 * shared with the Blade board. This layer adds the per-column label, color, and
 * count that a JSON client needs but Blade reads from `Task::STATUSES` itself.
 */
class BoardController extends ApiController
{
    use ReadsTaskFilters;

    public function __construct(private readonly BoardService $board) {}

    public function index(Request $request): JsonResponse
    {
        $releaseId = $this->idOrNoneFilter($request, 'release_id');
        $teamId = $this->idOrNoneFilter($request, 'team_id');
        $assigneeId = $this->filterId($request, 'assignee_id');
        $fields = $this->fieldFilters($request);

        $grouped = $this->board->columns([
            'release_id' => $releaseId,
            'team_id' => $teamId,
            'assignee_id' => $assigneeId,
        ] + $fields, $request->user());

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
                'team_id' => $teamId,
                'assignee_id' => $assigneeId,
            ] + $fields,
        ]);
    }

    /**
     * Quick-add straight onto a column. `release_id` and `team_id` are
     * optional; a linked card takes its release's team.
     */
    public function storeTask(BoardTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $task = $this->board->quickAdd(
            isset($data['release_id']) ? Release::findOrFail($data['release_id']) : null,
            $data['title'],
            $data['status'],
            $request->user(),
            isset($data['team_id']) ? (int) $data['team_id'] : null,
        );

        return $this->created(
            new TaskResource($task->load(['release.project', 'team', 'assignee'])->loadCount('comments')),
            'Task added to the board.'
        );
    }

    /** A drag: status change and column reorder in one atomic step. */
    public function move(MoveTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $this->board->move(
            $task,
            $request->string('status')->toString(),
            $request->input('ordered_ids', []),
            $request->user(),
        );

        return $this->ok(
            new TaskResource($task->fresh()->load(['release.project', 'team', 'assignee'])->loadCount('comments')),
            'Card moved.'
        );
    }

    /** Admins only. Its subtasks and comments go with it. */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('deleteFromBoard', $task);

        return $this->deleted($this->board->deleteCards([$task]));
    }

    /** Admins only; all of the ids or none. The request checks the role, then every id. */
    public function bulkDestroy(BulkDeleteTasksRequest $request): JsonResponse
    {
        return $this->deleted($this->board->deleteCards($request->tasks()));
    }

    /** @param  array{deleted_ids: array<int, int>, subtasks_deleted: int}  $result */
    private function deleted(array $result): JsonResponse
    {
        $count = count($result['deleted_ids']);

        return $this->ok($result, $count.' '.Str::plural('task', $count).' deleted.');
    }
}

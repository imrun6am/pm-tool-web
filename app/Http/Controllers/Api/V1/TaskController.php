<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TaskRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Release;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Tasks and subtasks. Open to every authenticated user — collaboration is not
 * gated by the planning roles.
 */
class TaskController extends ApiController
{
    public function __construct(private readonly TaskService $tasks) {}

    /**
     * Tasks across releases. Defaults to top-level tasks only, since subtasks
     * travel with their parent; `include_subtasks=1` flattens them in.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request, $this->tasks->filtered($this->filters($request)), TaskResource::class);
    }

    /** One release's task tree. */
    public function indexForRelease(Request $request, Release $release): AnonymousResourceCollection
    {
        $query = $this->tasks->forRelease($release, $this->filters($request));

        return $this->paginate($request, $query, TaskResource::class);
    }

    public function show(Task $task): JsonResponse
    {
        $task->load([
            'release.project', 'release.team', 'assignee', 'creator',
            'parent', 'subtasks.assignee', 'comments.user',
        ])->loadCount(['comments', 'subtasks']);

        return $this->ok(new TaskResource($task));
    }

    public function store(TaskRequest $request, Release $release): JsonResponse
    {
        $task = $this->tasks->createForRelease($release, $request->validated(), $request->user());

        return $this->created($this->hydrate($task), 'Task added.');
    }

    /** One level of nesting only — the guard lives in the service. */
    public function storeSubtask(TaskRequest $request, Task $task): JsonResponse
    {
        $subtask = $this->tasks->createSubtask($task, $request->validated(), $request->user());

        return $this->created($this->hydrate($subtask), 'Subtask added.');
    }

    public function update(TaskRequest $request, Task $task): JsonResponse
    {
        $this->tasks->update($task, $request->validated());

        return $this->ok($this->hydrate($task), 'Task updated.');
    }

    /** The lightweight status change, for a checkbox or a context menu. */
    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $this->tasks->changeStatus($task, $data['status']);

        return $this->ok($this->hydrate($task), 'Status updated.');
    }

    public function destroy(Task $task): JsonResponse
    {
        $wasSubtask = $task->isSubtask();
        $task->delete(); // the model's deleting hook removes subtasks + comments

        return $this->message($wasSubtask ? 'Subtask deleted.' : 'Task deleted.');
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'include_subtasks' => $request->boolean('include_subtasks'),
            'release_id' => $this->filterId($request, 'release_id'),
            'assignee_id' => $this->filterId($request, 'assignee_id'),
            'parent_id' => $this->filterId($request, 'parent_id'),
            'status' => $request->input('status'),
            'phase' => $request->input('phase'),
            'due_before' => $request->filled('due_before') ? $request->date('due_before') : null,
            'due_after' => $request->filled('due_after') ? $request->date('due_after') : null,
            'overdue' => $request->boolean('overdue'),
        ];
    }

    private function hydrate(Task $task): TaskResource
    {
        return new TaskResource(
            $task->load(['assignee', 'creator', 'subtasks.assignee', 'release'])
                ->loadCount(['comments', 'subtasks'])
        );
    }
}

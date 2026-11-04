<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ReadsTaskFilters;
use App\Http\Requests\BulkUpdateTasksRequest;
use App\Http\Requests\TaskRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Release;
use App\Models\Task;
use App\Services\TaskDependencyService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tasks and subtasks. Open to every authenticated user — collaboration is not
 * gated by the planning roles. Deleting is the exception: admins only (TaskPolicy).
 */
class TaskController extends ApiController
{
    use ReadsTaskFilters;

    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskDependencyService $dependencies,
    ) {}

    /**
     * Tasks across releases. Defaults to top-level tasks only, since subtasks
     * travel with their parent; `include_subtasks=1` flattens them in.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request, $this->tasks->filtered($this->taskListFilters($request), $request->user()), TaskResource::class);
    }

    /** One release's task tree. */
    public function indexForRelease(Request $request, Release $release): AnonymousResourceCollection
    {
        $query = $this->tasks->forRelease($release, $this->taskListFilters($request), $request->user());

        return $this->paginate($request, $query, TaskResource::class);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $this->dependencies->loadLinks($task, $request->user());

        $task->load([
            'release.project', 'release.team', 'team', 'assignee', 'creator',
            'parent', 'subtasks.assignee', 'comments.user', 'labels',
        ])->loadCount(['comments', 'subtasks']);

        return $this->ok(new TaskResource($task));
    }

    /** Change many tasks at once: all of them or none. */
    public function bulkUpdate(BulkUpdateTasksRequest $request): JsonResponse
    {
        $count = $this->tasks->bulkUpdate($request->tasks(), $request->changes());

        return $this->ok(['updated_ids' => $request->tasks()->pluck('id')->all()], $count.' '.Str::plural('task', $count).' updated.');
    }

    /** A top-level task with an optional `release_id`. */
    public function store(TaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->validated(), $request->user());

        return $this->created($this->hydrate($task), 'Task added.');
    }

    /** A top-level task in this release (any `release_id` in the body is ignored). */
    public function storeForRelease(TaskRequest $request, Release $release): JsonResponse
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
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $this->tasks->changeStatus($task, $data['status']);

        return $this->ok($this->hydrate($task), 'Status updated.');
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $wasSubtask = $task->isSubtask();
        $task->delete(); // the model's deleting hook removes subtasks + comments

        return $this->message($wasSubtask ? 'Subtask deleted.' : 'Task deleted.');
    }

    private function hydrate(Task $task): TaskResource
    {
        return new TaskResource(
            $task->load(['assignee', 'creator', 'subtasks.assignee', 'release', 'team', 'labels'])
                ->loadCount(['comments', 'subtasks'])
        );
    }
}

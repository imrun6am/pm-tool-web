<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TaskBlockRequest;
use App\Http\Requests\TaskDependencyRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Task;
use App\Services\TaskDependencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The blocked flag and the tasks a task waits for. Every answer is the updated task. */
class TaskDependencyController extends ApiController
{
    public function __construct(private readonly TaskDependencyService $dependencies) {}

    public function block(TaskBlockRequest $request, Task $task): JsonResponse
    {
        $this->dependencies->flag($task, $request->validated('reason'));

        return $this->task($request, $task, 'Marked as blocked.');
    }

    public function unblock(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $this->dependencies->clearFlag($task);

        return $this->task($request, $task, 'No longer marked as blocked.');
    }

    public function store(TaskDependencyRequest $request, Task $task): JsonResponse
    {
        $this->dependencies->add($task, $request->blocker());

        return $this->task($request, $task, 'Now waiting for '.$request->blocker()->reference().'.');
    }

    public function destroy(Request $request, Task $task, Task $blocker): JsonResponse
    {
        $this->authorize('update', $task);

        $this->dependencies->remove($task, $blocker);

        return $this->task($request, $task, 'No longer waiting for '.$blocker->reference().'.');
    }

    private function task(Request $request, Task $task, string $message): JsonResponse
    {
        return $this->ok(new TaskResource($this->dependencies->loadLinks($task, $request->user())), $message);
    }
}

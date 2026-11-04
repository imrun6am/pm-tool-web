<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TaskTimeLogRequest;
use App\Http\Resources\V1\TaskTimeLogResource;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Services\TaskTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hours logged on a task. The totals include its subtasks. Entries are omitted
 * for a viewer, who sees totals only.
 */
class TaskTimeLogController extends ApiController
{
    public function __construct(private readonly TaskTimeService $time) {}

    public function index(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $payload = $this->time->summary($task);

        if ($request->user()->can('viewEntries', [TaskTimeLog::class, $task])) {
            $payload['entries'] = TaskTimeLogResource::collection($this->time->entries($task))->resolve($request);
        }

        return $this->ok($payload);
    }

    public function store(TaskTimeLogRequest $request, Task $task): JsonResponse
    {
        $log = $this->time->log($task, $request->user(), $request->validated());

        return $this->created(new TaskTimeLogResource($log->load('user')), 'Time logged.');
    }

    public function update(TaskTimeLogRequest $request, TaskTimeLog $timeLog): JsonResponse
    {
        $this->time->update($timeLog, $request->validated());

        return $this->ok(new TaskTimeLogResource($timeLog->load(['user', 'task'])), 'Time entry updated.');
    }

    public function destroy(TaskTimeLog $timeLog): JsonResponse
    {
        $this->authorize('delete', $timeLog);

        $this->time->delete($timeLog);

        return $this->message('Time entry deleted.');
    }
}

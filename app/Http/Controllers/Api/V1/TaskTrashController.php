<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RestoreTasksRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Task;
use App\Services\TaskTrashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/** Deleted tasks. Admins only. */
class TaskTrashController extends ApiController
{
    public function __construct(private readonly TaskTrashService $trash) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manageTrash', Task::class);

        return $this->paginate($request, $this->trash->listing(), TaskResource::class);
    }

    public function restore(RestoreTasksRequest $request): JsonResponse
    {
        $ids = $this->trash->restore($request->tasks());

        return $this->ok(['restored_ids' => $ids], count($ids).' '.Str::plural('task', count($ids)).' restored.');
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('manageTrash', Task::class);
        abort_unless($task->trashed(), 404);

        $this->trash->purge($task);

        return $this->message('Task permanently deleted.');
    }
}

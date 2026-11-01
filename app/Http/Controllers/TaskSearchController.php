<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** ⌘K task results: the tasks the viewer may see whose title matches. */
class TaskSearchController extends Controller
{
    public function __invoke(Request $request, TaskSearchService $search): JsonResponse
    {
        $tasks = $search->search($request->user(), (string) $request->query('q', ''));

        return response()->json([
            'data' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'status_label' => $task->statusLabel(),
                'release' => $task->release?->name,
                'url' => route('tasks.show', $task),
            ])->values(),
        ]);
    }
}

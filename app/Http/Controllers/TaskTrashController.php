<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreTasksRequest;
use App\Models\Task;
use App\Services\TaskTrashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TaskTrashController extends Controller
{
    public function __construct(private readonly TaskTrashService $trash) {}

    public function index(): View
    {
        $this->authorize('manageTrash', Task::class);

        return view('tasks.trash', ['tasks' => $this->trash->listing()->paginate(50)]);
    }

    /** Answers JSON for the board's Undo, and a redirect for the trash page. */
    public function restore(RestoreTasksRequest $request): JsonResponse|RedirectResponse
    {
        $ids = $this->trash->restore($request->tasks());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'restored_ids' => $ids]);
        }

        return back()->with('success', count($ids).' '.Str::plural('task', count($ids)).' restored.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('manageTrash', Task::class);
        abort_unless($task->trashed(), 404);

        $this->trash->purge($task);

        return back()->with('success', 'Task permanently deleted.');
    }
}

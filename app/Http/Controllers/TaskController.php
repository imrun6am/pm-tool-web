<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Release;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function store(TaskRequest $request, Release $release): RedirectResponse
    {
        $this->tasks->createForRelease($release, $request->validated(), $request->user());

        return back()->with('success', 'Task added.');
    }

    public function storeSubtask(TaskRequest $request, Task $task): RedirectResponse
    {
        // One level of nesting only. The web flow prefers a flash message over
        // the API's 422, so the guard is checked here before delegating.
        if ($task->isSubtask()) {
            return back()->with('error', 'A subtask cannot have its own subtasks.');
        }

        $this->tasks->createSubtask($task, $request->validated(), $request->user());

        return back()->with('success', 'Subtask added.');
    }

    public function show(Task $task): View
    {
        $task->load(['release.project', 'release.team', 'assignee', 'creator',
            'subtasks.assignee', 'comments.user', 'parent']);

        return view('tasks.show', [
            'task' => $task,
            'users' => $this->tasks->assignableUsers($task),
        ]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->update($task, $request->validated());

        return back()->with('success', 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $this->tasks->changeStatus($task, $data['status']);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $wasSubtask = $task->isSubtask();
        $task->delete();

        return back()->with('success', $wasSubtask ? 'Subtask deleted.' : 'Task deleted.');
    }
}

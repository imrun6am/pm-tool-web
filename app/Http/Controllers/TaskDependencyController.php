<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskBlockRequest;
use App\Http\Requests\TaskDependencyRequest;
use App\Models\Task;
use App\Services\TaskDependencyService;
use Illuminate\Http\RedirectResponse;

class TaskDependencyController extends Controller
{
    public function __construct(private readonly TaskDependencyService $dependencies) {}

    public function block(TaskBlockRequest $request, Task $task): RedirectResponse
    {
        $this->dependencies->flag($task, $request->validated('reason'));

        return back()->with('success', 'Marked as blocked.');
    }

    public function unblock(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->dependencies->clearFlag($task);

        return back()->with('success', 'No longer marked as blocked.');
    }

    public function store(TaskDependencyRequest $request, Task $task): RedirectResponse
    {
        $this->dependencies->add($task, $request->blocker());

        return back()->with('success', 'Now waiting for '.$request->blocker()->reference().'.');
    }

    public function destroy(Task $task, Task $blocker): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->dependencies->remove($task, $blocker);

        return back()->with('success', 'No longer waiting for '.$blocker->reference().'.');
    }
}

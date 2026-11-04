<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskTimeLogRequest;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Services\TaskTimeService;
use Illuminate\Http\RedirectResponse;

class TaskTimeLogController extends Controller
{
    public function __construct(private readonly TaskTimeService $time) {}

    public function store(TaskTimeLogRequest $request, Task $task): RedirectResponse
    {
        $this->time->log($task, $request->user(), $request->validated());

        return back()->with('success', 'Time logged.');
    }

    public function update(TaskTimeLogRequest $request, TaskTimeLog $timeLog): RedirectResponse
    {
        $this->time->update($timeLog, $request->validated());

        return back()->with('success', 'Time entry updated.');
    }

    public function destroy(TaskTimeLog $timeLog): RedirectResponse
    {
        $this->authorize('delete', $timeLog);

        $this->time->delete($timeLog);

        return back()->with('success', 'Time entry deleted.');
    }
}

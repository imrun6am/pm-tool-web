<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\CelebrationService;
use App\Services\TasksheetResultSync;
use Illuminate\Support\Facades\Auth;

/**
 * Reflects board moves in the assignee's day-end result, from any controller,
 * service or API endpoint. Writes without a signed-in user (seeders, console)
 * do nothing, and a failure here is reported but never blocks the move.
 */
class TaskTasksheetObserver
{
    public function __construct(private readonly TasksheetResultSync $sync) {}

    public function created(Task $task): void
    {
        if (Auth::check() && $task->status === 'done') {
            rescue(fn () => $this->sync->taskFinished($task));
        }
    }

    public function updated(Task $task): void
    {
        if (! Auth::check() || ! $task->wasChanged('status')) {
            return;
        }

        $from = $task->getOriginal('status');

        if (CelebrationService::isCompletion($from, $task->status)) {
            rescue(fn () => $this->sync->taskFinished($task));
        } elseif ($from === 'done' && $task->status !== 'archive') {
            rescue(fn () => $this->sync->taskReopened($task));
        }
    }
}

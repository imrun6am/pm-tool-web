<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\CelebrationService;
use App\Services\NotificationService;
use App\Support\PendingCelebration;
use Illuminate\Support\Facades\Auth;

/**
 * Turns task writes — from any controller, service or API endpoint — into
 * inbox notifications and the completion moment. Only gathers facts; the
 * rules live in the services. Writes without a signed-in user (seeders,
 * console) do neither.
 */
class TaskEngagementObserver
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CelebrationService $celebrations,
        private readonly PendingCelebration $pending,
    ) {}

    public function created(Task $task): void
    {
        $this->notifications->taskAssigned($task, Auth::user());
    }

    public function updated(Task $task): void
    {
        $actor = Auth::user();

        if ($task->wasChanged('assignee_id')) {
            $this->notifications->taskAssigned($task, $actor);

            if ($previous = (int) $task->getOriginal('assignee_id')) {
                $this->notifications->taskUnassigned($task, $previous, $actor);
            }
        }

        if ($actor && $task->wasChanged('blocked_at') && $task->blocked_at) {
            $this->notifications->taskBlocked($task, $actor);
        }

        $isOpen = ! in_array($task->status, Task::DONE_STATUSES, true);

        // Finishing a task clears its block too, but that isn't news.
        if ($task->wasChanged('blocked_at') && ! $task->blocked_at && $isOpen) {
            $this->notifications->taskBlockCleared($task, $actor);
        }

        if ($actor && $task->wasChanged('status')
            && ($task->status === 'recheck' || ($task->getOriginal('status') === 'done' && $isOpen))) {
            $this->notifications->taskSentBack($task, $actor);
        }

        // A new assignee has just been told about the task as it now stands.
        if ($actor && $isOpen && ! $task->wasChanged('assignee_id')) {
            if ($task->wasChanged('due_date')) {
                $this->notifications->taskDueChanged($task, $actor);
            }
            if ($task->wasChanged('priority') && $task->priority === 'urgent') {
                $this->notifications->taskMarkedUrgent($task, $actor);
            }
        }

        if ($actor && $task->wasChanged('status') && in_array($task->status, Task::DONE_STATUSES, true)
            && ! in_array($task->getOriginal('status'), Task::DONE_STATUSES, true)) {
            $this->notifications->dependencyFinished($task, $actor);
        }

        if ($actor && $task->wasChanged('status')
            && CelebrationService::isCompletion($task->getOriginal('status'), $task->status)) {
            $celebration = $this->celebrations->for($task, $actor);
            if ($celebration !== null) {
                $this->pending->set($celebration);
            }
        }
    }

    /** To the trash only, and a subtask that went with its parent is covered by the parent's row. */
    public function deleted(Task $task): void
    {
        if (! $task->isForceDeleting() && ! $task->trashed_with_parent) {
            $this->notifications->taskDeleted($task, Auth::user());
        }
    }
}

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
        }

        if ($actor && $task->wasChanged('status')
            && CelebrationService::isCompletion($task->getOriginal('status'), $task->status)) {
            $celebration = $this->celebrations->for($task, $actor);
            if ($celebration !== null) {
                $this->pending->set($celebration);
            }
        }
    }
}

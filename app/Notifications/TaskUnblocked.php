<?php

namespace App\Notifications;

use App\Models\Task;

/** Everything this task was waiting on is finished. `$finished` is the last blocker to go. */
class TaskUnblocked extends TaskNotification
{
    public function __construct(Task $task, public readonly Task $finished)
    {
        parent::__construct($task);
    }

    public function kind(): string
    {
        return 'unblocked';
    }

    protected function message(): string
    {
        return "{$this->title()} is ready: {$this->finished->reference()} is finished";
    }
}

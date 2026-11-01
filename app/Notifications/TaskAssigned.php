<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;

class TaskAssigned extends TaskNotification
{
    public function __construct(Task $task, public readonly User $actor)
    {
        parent::__construct($task);
    }

    public function kind(): string
    {
        return 'assigned';
    }

    protected function message(): string
    {
        return "{$this->actor->name} assigned you {$this->title()}";
    }

    protected function extra(): array
    {
        return ['actor_id' => $this->actor->id, 'actor_name' => $this->actor->name];
    }
}

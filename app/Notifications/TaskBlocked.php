<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

class TaskBlocked extends TaskNotification
{
    public function __construct(Task $task, public readonly ?User $actor)
    {
        parent::__construct($task);
    }

    public function kind(): string
    {
        return 'blocked';
    }

    protected function message(): string
    {
        return ($this->actor?->name ?? 'Someone')." marked {$this->title()} as blocked";
    }

    protected function extra(): array
    {
        return [
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'excerpt' => Str::limit((string) $this->task->blocked_reason, 140),
        ];
    }
}

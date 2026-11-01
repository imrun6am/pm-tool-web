<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Support\Str;

class TaskCommented extends TaskNotification
{
    public function __construct(Task $task, public readonly Comment $comment)
    {
        parent::__construct($task);
    }

    public function kind(): string
    {
        return 'commented';
    }

    protected function message(): string
    {
        return "{$this->comment->user?->name} commented on {$this->title()}";
    }

    protected function extra(): array
    {
        $text = Str::of(strip_tags((string) $this->comment->body))->squish()->limit(140)->value();

        return [
            'actor_id' => $this->comment->user_id,
            'actor_name' => $this->comment->user?->name,
            'excerpt' => $text !== '' ? $text : null,
        ];
    }
}

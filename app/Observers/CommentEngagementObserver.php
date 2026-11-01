<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Task;
use App\Services\NotificationService;

/** A new comment on a task notifies the people involved in it. */
class CommentEngagementObserver
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function created(Comment $comment): void
    {
        if ($comment->commentable_type !== Task::class) {
            return;
        }

        $task = Task::find($comment->commentable_id);
        if ($task) {
            $this->notifications->taskCommented($task, $comment);
        }
    }
}

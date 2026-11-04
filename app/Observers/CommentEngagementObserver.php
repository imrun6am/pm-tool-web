<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Release;
use App\Models\Task;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

/**
 * A new comment on a task or release notifies the people involved in it.
 * Writes without a signed-in user (seeders, console) stay silent.
 */
class CommentEngagementObserver
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function created(Comment $comment): void
    {
        if (! Auth::check()) {
            return;
        }

        if ($comment->commentable_type === Task::class && ($task = Task::find($comment->commentable_id))) {
            $this->notifications->taskCommented($task, $comment);
        }

        if ($comment->commentable_type === Release::class && ($release = Release::find($comment->commentable_id))) {
            $this->notifications->releaseCommented($release, $comment);
        }
    }
}

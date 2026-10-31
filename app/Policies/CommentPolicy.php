<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * A comment may be edited or deleted only by its author or a lead.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id || $user->isLead();
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }
}

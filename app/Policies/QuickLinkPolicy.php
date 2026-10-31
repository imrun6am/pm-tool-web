<?php

namespace App\Policies;

use App\Models\QuickLink;
use App\Models\User;

class QuickLinkPolicy
{
    /**
     * Whether the link is within the user's reach at all — their own, or a
     * shared one when their role is allowed to see shared links. Mirrors the
     * model's `visibleTo` scope for a single record.
     */
    public function view(User $user, QuickLink $quickLink): bool
    {
        if ($quickLink->user_id === $user->id) {
            return true;
        }

        return $quickLink->isShared() && ! $user->hasLimitedAccess();
    }

    /**
     * Pinning is authorized by *visibility*, not ownership — the whole point is
     * to pin a teammate's shared link. It is not a write to the link but to the
     * user's own relationship with it, so it deliberately does not go through
     * `update` below.
     */
    public function pin(User $user, QuickLink $quickLink): bool
    {
        return $this->view($user, $quickLink);
    }

    /**
     * Only the author may edit or delete a quick link — bookmarks are
     * personal, so not even admins manage someone else's (NotePolicy stance).
     */
    public function update(User $user, QuickLink $quickLink): bool
    {
        return $quickLink->user_id === $user->id;
    }

    public function delete(User $user, QuickLink $quickLink): bool
    {
        return $this->update($user, $quickLink);
    }
}

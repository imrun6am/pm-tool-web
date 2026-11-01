<?php

namespace App\Policies;

use App\Models\Release;
use App\Models\User;

/**
 * Release detail and collaboration (documents, comments, tasks, links) are
 * open to every signed-in user except that developers and QA only reach their
 * own teams' releases. Planning writes stay behind the role middleware.
 */
class ReleasePolicy
{
    public function view(User $user, Release $release): bool
    {
        return $release->isVisibleTo($user);
    }
}

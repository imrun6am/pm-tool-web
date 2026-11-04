<?php

namespace App\Policies;

use App\Models\SavedView;
use App\Models\User;

/** Views belong to whoever saved them. Leads can share a view, and remove shared ones. */
class SavedViewPolicy
{
    public function share(User $user): bool
    {
        return $user->isLead();
    }

    public function delete(User $user, SavedView $view): bool
    {
        return $view->user_id === $user->id || ($view->is_shared && $user->isLead());
    }
}

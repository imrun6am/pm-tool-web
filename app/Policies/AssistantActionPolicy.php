<?php

namespace App\Policies;

use App\Models\AssistantAction;
use App\Models\User;

class AssistantActionPolicy
{
    /** Confirming or cancelling: only the person it was drafted for, with no lead or admin override. */
    public function resolve(User $user, AssistantAction $action): bool
    {
        return $action->user_id === $user->id;
    }
}

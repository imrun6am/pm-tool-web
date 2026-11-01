<?php

namespace App\Policies;

use App\Models\EventImport;
use App\Models\User;

/**
 * An import is the uploader's private draft. Admins may look at and remove
 * anyone's (support, cleanup), but only the uploader confirms or retries:
 * confirmed events are attributed to whoever uploaded the file.
 */
class EventImportPolicy
{
    public function view(User $user, EventImport $import): bool
    {
        return $import->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, EventImport $import): bool
    {
        return $this->view($user, $import);
    }

    public function confirm(User $user, EventImport $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function retry(User $user, EventImport $import): bool
    {
        return $import->user_id === $user->id;
    }
}

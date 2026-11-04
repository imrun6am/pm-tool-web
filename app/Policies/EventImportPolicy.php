<?php

namespace App\Policies;

use App\Models\EventImport;
use App\Models\User;

/**
 * An import is the uploader's private draft. Admins may look at and remove
 * anyone's (support, cleanup), but only the uploader confirms or retries:
 * confirmed events are attributed to whoever uploaded the file.
 *
 * An import from a note adds one rule to each: an admin may not open one from
 * a note they can't read (its proposals quote the note, and a private note is
 * closed even to admins), and a retry needs the starter to still read the note.
 */
class EventImportPolicy
{
    public function view(User $user, EventImport $import): bool
    {
        return $import->user_id === $user->id || ($user->isAdmin() && $import->originReadableBy($user));
    }

    /** Removing an import reveals nothing, so admins keep this for cleanup. */
    public function delete(User $user, EventImport $import): bool
    {
        return $import->user_id === $user->id || $user->isAdmin();
    }

    public function confirm(User $user, EventImport $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function retry(User $user, EventImport $import): bool
    {
        return $import->user_id === $user->id && $import->originReadableBy($user);
    }
}

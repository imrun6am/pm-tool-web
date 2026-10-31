<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    /**
     * Only the author may edit or delete a note — a private note is personal,
     * so not even admins manage someone else's notes.
     */
    public function update(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }
}

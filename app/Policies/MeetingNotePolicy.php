<?php

namespace App\Policies;

use App\Models\DailyStandup;
use App\Models\MeetingNote;
use App\Models\User;

/**
 * Meeting notes are shared team records, so — unlike NotePolicy, where not
 * even leads may touch someone else's personal note — a lead may delete any
 * meeting note (e.g. cleanup after someone leaves). Editing stays author-only,
 * except a generated standup, which has no author: anyone who may generate it may
 * add to it.
 */
class MeetingNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MeetingNote $meetingNote): bool
    {
        // Attendees-only notes are limited to attendees, author, and leads.
        return $meetingNote->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MeetingNote $meetingNote): bool
    {
        if ($meetingNote->created_by === $user->id) {
            return true;
        }

        $team = $meetingNote->standup?->team;

        return $team !== null && $user->can('generate', [DailyStandup::class, $team]);
    }

    public function delete(User $user, MeetingNote $meetingNote): bool
    {
        return $meetingNote->created_by === $user->id || $user->isLead();
    }
}

<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /** Private events: creator and attendees only. Developers and QA: their teams' events (Event::scopeVisibleTo). */
    public function view(User $user, Event $event): bool
    {
        return $event->isVisibleTo($user);
    }

    /** Only the person who added an event may change it; there is no lead or admin override. */
    public function update(User $user, Event $event): bool
    {
        return $event->created_by === $user->id;
    }

    /**
     * The creator, or an admin removing a shared event (clean-up). Someone
     * else's private event is out of an admin's reach, as it is out of sight.
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event) || ($user->isAdmin() && ! $event->isPrivate());
    }
}

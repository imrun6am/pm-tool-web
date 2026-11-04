<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

/** Deleting an event tells its attendees, from the web or the API. */
class EventEngagementObserver
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function deleting(Event $event): void
    {
        $this->notifications->eventCancelled($event, Auth::user());
    }
}

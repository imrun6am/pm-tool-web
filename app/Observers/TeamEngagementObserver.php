<?php

namespace App\Observers;

use App\Models\Team;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

/** A change of team lead tells both people, whichever form or endpoint made it. */
class TeamEngagementObserver
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function created(Team $team): void
    {
        if ($team->team_lead_id) {
            $this->notifications->teamLeadChanged($team, $team->team_lead_id, null, Auth::user());
        }
    }

    public function updated(Team $team): void
    {
        if ($team->wasChanged('team_lead_id')) {
            $this->notifications->teamLeadChanged($team, $team->team_lead_id, $team->getOriginal('team_lead_id'), Auth::user());
        }
    }
}

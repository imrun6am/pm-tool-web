<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The authenticated user as returned by `GET /me` — the account plus the
 * effective permissions the desktop client builds its navigation from.
 *
 * The permission flags are read straight off the domain model's capability
 * methods, so the client's menu and the server's enforcement can never
 * disagree: both answer to `User::isLead()` and friends. The client uses these
 * to decide what to *show*; the server still refuses anything it must, because
 * a hidden button is not a permission check.
 *
 * @mixin User
 */
class AuthUserResource extends UserResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'permissions' => [
                'manage_users' => $this->canManageUsers(),
                'manage_releases' => $this->canManageReleases(),
                'manage_workspace' => $this->canManageWorkspace(),
                'manage_team_members' => $this->canManageTeamMembers(),
                'manage_competencies' => $this->canManageCompetencies(),
                'oversee_all_teams' => $this->canOverseeAllTeams(),
                'is_admin' => $this->isAdmin(),
                'is_lead' => $this->isLead(),
                'has_limited_access' => $this->hasLimitedAccess(),
                'is_viewer' => $this->isViewer(),
            ],
            'teams' => TeamSummaryResource::collection($this->whenLoaded('teams')),
            'led_teams' => TeamSummaryResource::collection($this->whenLoaded('ledTeams')),
        ]);
    }
}

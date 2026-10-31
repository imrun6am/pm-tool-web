<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TeamResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Team membership and lead assignment.
 *
 * Membership is never erased — removing someone stamps their departure so the
 * team's past tasksheets still know they were there. That rule lives in
 * TeamService, shared with the web team page.
 */
class TeamMemberController extends ApiController
{
    public function __construct(private readonly TeamService $teams) {}

    /** Current members, plus the active users eligible to be added. */
    public function index(Request $request, Team $team): JsonResponse
    {
        $team->load('members');

        return $this->ok([
            'members' => UserSummaryResource::collection($team->members)->resolve($request),
            'assignable_users' => UserSummaryResource::collection(
                $this->teams->assignableUsers($team)
            )->resolve($request),
        ]);
    }

    /** Add a member, restoring a previous membership rather than duplicating it. */
    public function store(Request $request, Team $team): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ]);

        $user = $this->teams->addMember($team, $data['user_id']);

        return $this->ok(
            new TeamResource($team->load('members')),
            "{$user->name} added to {$team->name}."
        );
    }

    /** Soft leave: stamp the departure, keep the row. */
    public function destroy(Team $team, User $user): JsonResponse
    {
        $this->teams->removeMember($team, $user);

        return $this->ok(
            new TeamResource($team->load('members')),
            "{$user->name} removed from {$team->name}."
        );
    }

    /** Assign or clear the team lead — any active user, regardless of role. */
    public function updateLead(Request $request, Team $team): JsonResponse
    {
        $data = $request->validate([
            'team_lead_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ]);

        $this->teams->updateLead($team, $data['team_lead_id'] ?? null);

        return $this->ok(
            new TeamResource($team),
            $team->team_lead_id
                ? "{$team->teamLead->name} set as lead of {$team->name}."
                : "Team lead cleared for {$team->name}."
        );
    }
}

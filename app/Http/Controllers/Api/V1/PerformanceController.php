<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesPerformanceTeams;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\PerformanceScore;
use App\Models\Team;
use App\Models\User;
use App\Services\PerformanceAnalytics;
use App\Support\PerformancePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Performance analytics — team overview and member scorecards.
 *
 * Every route here is behind the `lead` middleware, and the team resolution
 * below narrows further: an org-level lead reaches any team, a team lead only
 * the teams they are the assigned lead of. Requesting a team outside that set
 * does not silently fall back to an allowed one — it 403s, because quietly
 * answering a different question than the one asked is worse than refusing.
 */
class PerformanceController extends ApiController
{
    use ResolvesPerformanceTeams;

    /** The teams this lead may evaluate, their own first. */
    public function teams(Request $request): JsonResponse
    {
        $teams = $this->accessiblePerformanceTeams($request->user());

        return $this->ok(TeamSummaryResource::collection($teams));
    }

    /** A team's overview for the week containing `week` (default: this week). */
    public function overview(Request $request, PerformanceAnalytics $analytics): JsonResponse
    {
        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'week' => ['nullable', 'date'],
        ]);

        $viewer = $request->user();
        $teams = $this->accessiblePerformanceTeams($viewer);
        $team = $this->requireTeam($teams, $this->filterId($request, 'team_id'));

        $weekDate = $request->filled('week') ? Carbon::parse($request->input('week')) : today();

        return $this->ok([
            'teams' => TeamSummaryResource::collection($teams)->resolve($request),
            'team' => $team ? (new TeamSummaryResource($team))->resolve($request) : null,
            'week' => [
                'date' => $weekDate->toDateString(),
                'label' => PerformancePeriod::weekLabel($weekDate),
                'start' => PerformancePeriod::week($weekDate)['start']->toDateString(),
                'end' => PerformancePeriod::week($weekDate)['end']->toDateString(),
                'prev' => $weekDate->copy()->subWeek()->toDateString(),
                'next' => $weekDate->copy()->addWeek()->toDateString(),
            ],
            'overview' => $team ? $analytics->teamOverview($team, $weekDate) : null,
        ]);
    }

    /** One member's scorecard within a team, for a week. */
    public function member(Request $request, User $user, PerformanceAnalytics $analytics): JsonResponse
    {
        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'week' => ['nullable', 'date'],
        ]);

        $team = $this->resolveMemberTeam($request->user(), $user, $this->filterId($request, 'team_id'));
        abort_if($team === null, 403, 'You cannot view this member’s performance.');

        $weekDate = $request->filled('week') ? Carbon::parse($request->input('week')) : today();

        return $this->ok([
            'member' => (new UserSummaryResource($user))->resolve($request),
            'team' => (new TeamSummaryResource($team))->resolve($request),
            'week' => [
                'date' => $weekDate->toDateString(),
                'label' => PerformancePeriod::weekLabel($weekDate),
                'prev' => $weekDate->copy()->subWeek()->toDateString(),
                'next' => $weekDate->copy()->addWeek()->toDateString(),
            ],
            'scorecard' => $analytics->memberScorecard($user, $team, $weekDate),
        ]);
    }

    /**
     * Resolve an explicitly requested team, refusing rather than substituting.
     * With no `team_id` at all, the first accessible team is a sensible default.
     *
     * @param  Collection<int, Team>  $teams
     */
    private function requireTeam($teams, ?int $teamId): ?Team
    {
        if ($teamId === null) {
            return $teams->first();
        }

        $team = $teams->firstWhere('id', $teamId);

        abort_if($team === null, 403, 'You cannot view performance for that team.');

        return $team;
    }

    /**
     * A team the viewer may access in which the member is a developer or QA —
     * or in which they already have scores, so a departed member's record stays
     * reachable.
     */
    private function resolveMemberTeam(User $viewer, User $member, ?int $teamId): ?Team
    {
        $candidates = $this->accessiblePerformanceTeams($viewer)
            ->filter(function (Team $team) use ($member) {
                $isMember = $team->members()
                    ->whereKey($member->id)
                    ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
                    ->exists();

                $hasScores = PerformanceScore::where('team_id', $team->id)
                    ->where('user_id', $member->id)
                    ->exists();

                return $isMember || $hasScores;
            })
            ->values();

        if ($teamId !== null) {
            return $candidates->firstWhere('id', $teamId);
        }

        return $candidates->first();
    }
}

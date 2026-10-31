<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPerformanceTeams;
use App\Models\PerformanceScore;
use App\Models\Team;
use App\Models\User;
use App\Services\PerformanceAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    use ResolvesPerformanceTeams;

    /** Team performance overview for a week. */
    public function index(Request $request, PerformanceAnalytics $analytics): View
    {
        $viewer = $request->user();
        $teams = $this->accessiblePerformanceTeams($viewer);
        $team = $this->resolvePerformanceTeam($teams, $request->integer('team') ?: null);

        $weekDate = ($d = $request->input('week')) ? Carbon::parse($d) : today();

        return view('performance.index', [
            'teams' => $teams,
            'team' => $team,
            'weekDate' => $weekDate,
            'prevWeek' => $weekDate->copy()->subWeek(),
            'nextWeek' => $weekDate->copy()->addWeek(),
            'overview' => $team ? $analytics->teamOverview($team, $weekDate) : null,
        ]);
    }

    /** One member's scorecard within a team, for a week. */
    public function show(Request $request, User $user, PerformanceAnalytics $analytics): View
    {
        $viewer = $request->user();

        // Resolve the team context: an explicit ?team= the viewer may access and
        // the member belongs to (or has been scored in), else the member's first
        // such team.
        $team = $this->resolveMemberTeam($viewer, $user, $request->integer('team') ?: null);
        abort_if($team === null, 403, 'You cannot view this member’s performance.');

        $weekDate = ($d = $request->input('week')) ? Carbon::parse($d) : today();

        return view('performance.show', [
            'member' => $user,
            'team' => $team,
            'weekDate' => $weekDate,
            'prevWeek' => $weekDate->copy()->subWeek(),
            'nextWeek' => $weekDate->copy()->addWeek(),
            'card' => $analytics->memberScorecard($user, $team, $weekDate),
        ]);
    }

    /**
     * A team the viewer may access AND in which the member is a dev/QA (or has
     * scores). Preference: the requested team, else the first that qualifies.
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
                    ->where('user_id', $member->id)->exists();

                return $isMember || $hasScores;
            })
            ->values();

        return ($teamId ? $candidates->firstWhere('id', $teamId) : null) ?? $candidates->first();
    }
}

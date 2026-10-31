<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPerformanceTeams;
use App\Http\Requests\PerformanceScoreRequest;
use App\Services\PerformanceEvaluationService;
use App\Support\PerformancePeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PerformanceScoreController extends Controller
{
    use ResolvesPerformanceTeams;

    public function __construct(private readonly PerformanceEvaluationService $evaluation) {}

    /**
     * The evaluation grid: a member × competency matrix for one team, cadence,
     * and period, where the assigned lead records 1–5 ratings.
     */
    public function evaluate(Request $request): View
    {
        $viewer = $request->user();
        $teams = $this->accessiblePerformanceTeams($viewer);
        $team = $this->resolvePerformanceTeam($teams, $request->integer('team') ?: null);

        $grid = $this->evaluation->grid($team, $request->input('cadence'), $request->input('date'));

        return view('performance.evaluate', [
            'teams' => $teams,
            'team' => $team,
            ...$grid,
        ]);
    }

    /** Upsert one member's row of ratings for a cadence + period. */
    public function upsert(PerformanceScoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $period = PerformancePeriod::normalize($data['cadence'], Carbon::parse($data['date']));
        $notes = $data['notes'] ?? [];

        $saved = 0;
        foreach ($this->evaluation->ratedCells($data['scores'] ?? []) as $competencyId => $value) {
            $score = $this->evaluation->resolveScore(
                (int) $data['team_id'],
                (int) $data['user_id'],
                $competencyId,
                $period,
            );

            $this->authorize('update', $score);

            $this->evaluation->saveScore(
                $score,
                $value,
                $notes[$competencyId] ?? null,
                $request->user(),
                $period,
            );

            $saved++;
        }

        return redirect()->route('performance.evaluate', [
            'team' => $data['team_id'],
            'cadence' => $data['cadence'],
            'date' => $period['start']->toDateString(),
        ])->with('success', $saved > 0 ? 'Ratings saved.' : 'No ratings to save.');
    }
}

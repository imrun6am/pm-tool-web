<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesPerformanceTeams;
use App\Http\Requests\PerformanceScoreRequest;
use App\Http\Resources\V1\PerformanceCompetencyResource;
use App\Http\Resources\V1\PerformanceScoreResource;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\User;
use App\Services\PerformanceEvaluationService;
use App\Support\PerformancePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The evaluation grid and the ratings upsert.
 *
 * Period normalization, the grid roster, the leave flags, and the upsert
 * semantics all live in PerformanceEvaluationService, shared with the Blade
 * evaluation screen. Authorization stays here, per score row, against the
 * (possibly unsaved) row's team.
 */
class PerformanceScoreController extends ApiController
{
    use ResolvesPerformanceTeams;

    public function __construct(private readonly PerformanceEvaluationService $evaluation) {}

    /**
     * The member × competency matrix for one team, cadence, and period, with
     * any existing ratings and a leave marker per member.
     */
    public function grid(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'cadence' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
        ]);

        $teams = $this->accessiblePerformanceTeams($request->user());

        $teamId = $this->filterId($request, 'team_id');
        $team = $teamId ? $teams->firstWhere('id', $teamId) : $teams->first();
        abort_if($teamId !== null && $team === null, 403, 'You cannot evaluate that team.');

        $grid = $this->evaluation->grid($team, $request->input('cadence'), $request->input('date'));

        return $this->ok([
            'teams' => TeamSummaryResource::collection($teams)->resolve($request),
            'team' => $team ? (new TeamSummaryResource($team))->resolve($request) : null,
            'cadence' => $grid['cadence'],
            'period' => [
                'type' => $grid['period']['type'],
                'date' => $grid['date']->toDateString(),
                'start' => $grid['period']['start']->toDateString(),
                'end' => $grid['period']['end']->toDateString(),
                'label' => $grid['periodLabel'],
                'prev' => $grid['prev']->toDateString(),
                'next' => $grid['next']->toDateString(),
                'is_future' => $grid['isFuture'],
            ],
            'competencies' => PerformanceCompetencyResource::collection($grid['competencies'])->resolve($request),
            'rows' => $this->presentRows($request, $grid),
        ]);
    }

    /**
     * Upsert one member's ratings for a cadence and period. Blank cells are
     * skipped by the service rather than stored as zero.
     */
    public function upsert(PerformanceScoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $period = PerformancePeriod::normalize($data['cadence'], Carbon::parse($data['date']));
        $notes = $data['notes'] ?? [];

        $saved = [];

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

            $saved[] = $score->load(['competency', 'evaluator']);
        }

        return $this->ok(
            PerformanceScoreResource::collection(collect($saved)),
            $saved ? 'Ratings saved.' : 'No ratings to save.'
        );
    }

    /**
     * One row per member: their leave marker, the competencies that apply to
     * their role, and any scores already recorded.
     *
     * @param  array<string, mixed>  $grid
     * @return list<array<string, mixed>>
     */
    private function presentRows(Request $request, array $grid): array
    {
        $rows = [];

        foreach ($grid['rowUsers'] as $member) {
            $rows[] = [
                'member' => (new UserSummaryResource($member))->resolve($request),
                'on_leave' => $grid['onLeave'][$member->id] ?? false,
                'applicable_competency_ids' => $grid['competencies']
                    ->filter(fn (PerformanceCompetency $c) => $c->appliesToRole($member->role))
                    ->pluck('id')->values()->all(),
                'scores' => $this->memberScores($request, $grid, $member),
            ];
        }

        return $rows;
    }

    /**
     * A member's recorded scores keyed by competency id.
     *
     * The shared grid keys its scores "{userId}-{competencyId}" because that is
     * what a two-dimensional Blade table indexes by; this API nests them under
     * the member instead, so the key is re-derived here.
     *
     * @param  array<string, mixed>  $grid
     * @return array<int, array<string, mixed>>
     */
    private function memberScores(Request $request, array $grid, User $member): array
    {
        $scores = [];

        foreach ($grid['competencies'] as $competency) {
            $score = $grid['scores']->get($member->id.'-'.$competency->id);

            if ($score instanceof PerformanceScore) {
                $scores[$competency->id] = (new PerformanceScoreResource($score))->resolve($request);
            }
        }

        return $scores;
    }
}

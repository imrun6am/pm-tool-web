<?php

namespace App\Services;

use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\PerformancePeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The evaluation grid and the ratings write.
 *
 * A score is keyed on (team, member, competency, period), where the period comes
 * from the competency's cadence: the day for daily competencies, the
 * Monday–Sunday week for weekly ones. Saving the same cell twice updates it
 * rather than accumulating duplicates.
 *
 * Blank cells are skipped, never stored as zero — a lead may rate a few
 * competencies now and the rest later without the unrated ones dragging the
 * member's average down.
 */
class PerformanceEvaluationService
{
    /** The competencies that apply to a cadence, in catalog order. */
    public function competenciesFor(string $cadence): Collection
    {
        return PerformanceCompetency::active()->forCadence($cadence)->ordered()->get();
    }

    /** Normalize a cadence string, defaulting to weekly. */
    public function normalizeCadence(?string $cadence): string
    {
        return $cadence === PerformanceCompetency::CADENCE_DAILY
            ? PerformanceCompetency::CADENCE_DAILY
            : PerformanceCompetency::CADENCE_WEEKLY;
    }

    /**
     * The scores already recorded for a team in a period, keyed
     * "{userId}-{competencyId}" — the shape the grid indexes cells by.
     *
     * @param  array{type: string, start: Carbon, end: Carbon}  $period
     * @return Collection<string, PerformanceScore>
     */
    public function scoresFor(Team $team, array $period): Collection
    {
        return PerformanceScore::where('team_id', $team->id)
            ->whereDate('period_start', $period['start']->toDateString())
            ->get()
            ->keyBy(fn (PerformanceScore $s) => $s->user_id.'-'.$s->competency_id);
    }

    /**
     * The members to show: the team's active developers and QA, plus anyone
     * already scored this period — so a former member's record stays editable.
     *
     * @param  Collection<string, PerformanceScore>  $scores
     * @return Collection<int, User>
     */
    public function rowUsersFor(Team $team, Collection $scores): Collection
    {
        $members = $team->members()
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
            ->active()
            ->get();

        $scoredUserIds = $scores->pluck('user_id')->unique()->all();

        $extra = empty($scoredUserIds)
            ? collect()
            : User::whereIn('id', $scoredUserIds)
                ->whereNotIn('id', $members->pluck('id')->all())
                ->get();

        return $members->concat($extra)->unique('id')->sortBy('name')->values();
    }

    /**
     * Who was absent for the whole period — on leave with no working day in it.
     *
     * Bounded with `whereDate`, not `whereBetween`: the `date` cast stores a
     * midnight timestamp, which a plain 'Y-m-d' upper bound would sort before
     * and so exclude the period's final day.
     *
     * @param  Collection<int, User>  $rowUsers
     * @param  array{type: string, start: Carbon, end: Carbon}  $period
     * @return array<int, bool>
     */
    public function leaveFlags(Team $team, Collection $rowUsers, array $period): array
    {
        $entries = TasksheetEntry::where('team_id', $team->id)
            ->whereIn('user_id', $rowUsers->pluck('id'))
            ->whereDate('date', '>=', $period['start']->toDateString())
            ->whereDate('date', '<=', $period['end']->toDateString())
            ->get()
            ->groupBy('user_id');

        $flags = [];

        foreach ($rowUsers as $member) {
            $memberEntries = $entries->get($member->id, collect());
            $present = $memberEntries->filter(fn (TasksheetEntry $e) => ! $e->isOnLeave())->count();
            $leave = $memberEntries->filter(fn (TasksheetEntry $e) => $e->isOnLeave())->count();

            $flags[$member->id] = $leave > 0 && $present === 0;
        }

        return $flags;
    }

    /**
     * Everything one evaluation grid needs, assembled in one call.
     *
     * @return array{
     *     cadence: string,
     *     isWeekly: bool,
     *     date: Carbon,
     *     period: array{type: string, start: Carbon, end: Carbon},
     *     periodLabel: string,
     *     prev: Carbon,
     *     next: Carbon,
     *     isFuture: bool,
     *     competencies: Collection<int, PerformanceCompetency>,
     *     rowUsers: Collection<int, User>,
     *     scores: Collection<string, PerformanceScore>,
     *     onLeave: array<int, bool>
     * }
     */
    public function grid(?Team $team, ?string $cadence, ?string $date): array
    {
        $cadence = $this->normalizeCadence($cadence);
        $isWeekly = $cadence === PerformanceCompetency::CADENCE_WEEKLY;

        $anchor = $date ? Carbon::parse($date)->startOfDay() : today();
        $period = PerformancePeriod::normalize($cadence, $anchor);

        $competencies = $this->competenciesFor($cadence);

        $scores = collect();
        $rowUsers = collect();
        $onLeave = [];

        if ($team) {
            $scores = $this->scoresFor($team, $period);
            $rowUsers = $this->rowUsersFor($team, $scores);
            $onLeave = $this->leaveFlags($team, $rowUsers, $period);
        }

        return [
            'cadence' => $cadence,
            'isWeekly' => $isWeekly,
            'date' => $anchor,
            'period' => $period,
            'periodLabel' => $isWeekly
                ? PerformancePeriod::weekLabel($anchor)
                : $anchor->format('l, M j, Y'),
            'prev' => $isWeekly ? $anchor->copy()->subWeek() : $anchor->copy()->subDay(),
            'next' => $isWeekly ? $anchor->copy()->addWeek() : $anchor->copy()->addDay(),
            'isFuture' => $period['start']->gt(today()),
            'competencies' => $competencies,
            'rowUsers' => $rowUsers,
            'scores' => $scores,
            'onLeave' => $onLeave,
        ];
    }

    /**
     * Find or build one score row so the caller can authorize it against the
     * (possibly unsaved) row's team before the write.
     *
     * Manual find + save, as the tasksheet does, to sidestep date-cast
     * comparison quirks across drivers on the unique key.
     *
     * @param  array{type: string, start: Carbon, end: Carbon}  $period
     */
    public function resolveScore(int $teamId, int $userId, int|string $competencyId, array $period): PerformanceScore
    {
        $score = PerformanceScore::where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where('competency_id', $competencyId)
            ->whereDate('period_start', $period['start']->toDateString())
            ->first()
            ?? new PerformanceScore([
                'team_id' => $teamId,
                'user_id' => $userId,
                'competency_id' => $competencyId,
            ]);

        // Authorized against the row's team, loaded even for an unsaved row.
        $score->setRelation('team', Team::find($teamId));

        return $score;
    }

    /**
     * @param  array{type: string, start: Carbon, end: Carbon}  $period
     */
    public function saveScore(PerformanceScore $score, int $value, ?string $note, User $evaluator, array $period): PerformanceScore
    {
        $score->fill([
            'evaluator_id' => $evaluator->id,
            'period_type' => $period['type'],
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
            'score' => $value,
            'note' => $note,
        ])->save();

        return $score;
    }

    /**
     * The rated cells of a submitted grid row, blanks dropped.
     *
     * @param  array<int|string, mixed>  $scores
     * @return array<int|string, int>
     */
    public function ratedCells(array $scores): array
    {
        return collect($scores)
            ->reject(fn ($value) => $value === null || $value === '')
            ->map(fn ($value) => (int) $value)
            ->all();
    }
}

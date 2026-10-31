<?php

namespace App\Services;

use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\PerformancePeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes performance analytics for a member or a team over a weekly window.
 *
 * The blended headline score is the weight-weighted average of the member's
 * *rated* competencies — competencies without a rating this week are excluded
 * (renormalized), never treated as zero, so an incomplete evaluation never
 * drags the number down. Objective panels (tasksheet, board) are computed
 * separately and never fold into the rating score. Every average guards an
 * empty denominator and returns null rather than dividing by zero.
 */
class PerformanceAnalytics
{
    /** How many weeks of history to chart in trends. */
    public const TREND_WEEKS = 8;

    /** Below this weekly overall score, a member is flagged for attention. */
    public const ATTENTION_THRESHOLD = 3.0;

    /**
     * A member's full scorecard for the week containing $weekDate.
     *
     * @return array<string, mixed>
     */
    public function memberScorecard(User $member, Team $team, Carbon $weekDate): array
    {
        $week = PerformancePeriod::week($weekDate);
        $weeks = PerformancePeriod::recentWeeks($weekDate, self::TREND_WEEKS);
        $competencies = $this->applicableCompetencies($member->role);

        $scores = PerformanceScore::with(['evaluator', 'competency'])
            ->where('team_id', $team->id)
            ->where('user_id', $member->id)
            ->whereDate('period_start', '>=', $weeks[0]['start']->toDateString())
            ->whereDate('period_start', '<=', $week['end']->toDateString())
            ->get();

        // Representative (per-competency) score for each week: daily competencies
        // are averaged across the days rated that week; weekly ones are the one score.
        $repByWeek = [];
        foreach ($weeks as $i => $w) {
            $repByWeek[$i] = $this->repScores($this->scoresInWeek($scores, $w));
        }
        $currentRep = end($repByWeek) ?: [];

        // Overall trend across the charted weeks.
        $overallTrend = [];
        foreach ($weeks as $i => $w) {
            $overallTrend[] = [
                'label' => $w['label'],
                'value' => $this->weightedAverage($repByWeek[$i], $competencies),
            ];
        }

        // Per-competency detail.
        $currentWeekScores = $this->scoresInWeek($scores, $week);
        $competencyRows = $competencies->map(function (PerformanceCompetency $c) use ($currentRep, $repByWeek, $weeks, $scores, $currentWeekScores) {
            $latestInWeek = $currentWeekScores->where('competency_id', $c->id)
                ->sortByDesc('updated_at')->first();
            $latestEver = $scores->where('competency_id', $c->id)
                ->sortByDesc(fn (PerformanceScore $s) => $s->period_start->timestamp)->first();

            return [
                'competency' => $c,
                'score' => $currentRep[$c->id] ?? null,
                'note' => $latestInWeek?->note,
                'evaluator' => $latestInWeek?->evaluator?->name,
                'latest' => $latestEver?->score,
                'trend' => array_map(fn ($i) => $repByWeek[$i][$c->id] ?? null, array_keys($weeks)),
            ];
        })->values();

        return [
            'week' => $week,
            'weekLabel' => PerformancePeriod::weekLabel($weekDate),
            'overall' => $this->weightedAverage($currentRep, $competencies),
            'categories' => $this->categoryAverages($currentRep, $competencies),
            'competencies' => $competencyRows,
            'ratedCount' => count($currentRep),
            'applicableCount' => $competencies->count(),
            'overallTrend' => $overallTrend,
            'history' => $this->history($scores),
            'tasksheet' => $this->tasksheetPanel($member, $team, $week),
            'board' => $this->boardPanel($member),
        ];
    }

    /**
     * A team's overview for the week containing $weekDate.
     *
     * @return array<string, mixed>
     */
    public function teamOverview(Team $team, Carbon $weekDate): array
    {
        $week = PerformancePeriod::week($weekDate);
        $weeks = PerformancePeriod::recentWeeks($weekDate, self::TREND_WEEKS);
        $prevWeek = count($weeks) >= 2 ? $weeks[count($weeks) - 2] : null;

        $members = $team->members()
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
            ->active()
            ->orderBy('name')
            ->get();

        $memberIds = $members->pluck('id');

        $scores = $memberIds->isEmpty() ? collect() : PerformanceScore::query()
            ->where('team_id', $team->id)
            ->whereIn('user_id', $memberIds)
            ->whereDate('period_start', '>=', $weeks[0]['start']->toDateString())
            ->whereDate('period_start', '<=', $week['end']->toDateString())
            ->get();
        $scoresByUser = $scores->groupBy('user_id');

        $leaveByUser = $this->weekLeaveByUser($team, $memberIds, $week);

        $rows = [];
        $sumCovered = 0;
        $sumExpected = 0;
        foreach ($members as $member) {
            $competencies = $this->applicableCompetencies($member->role);
            $mine = $scoresByUser->get($member->id, collect());

            $currentRep = $this->repScores($this->scoresInWeek($mine, $week));
            $overall = $this->weightedAverage($currentRep, $competencies);
            $prevOverall = $prevWeek
                ? $this->weightedAverage($this->repScores($this->scoresInWeek($mine, $prevWeek)), $competencies)
                : null;

            $onLeaveAllWeek = ($leaveByUser[$member->id] ?? false);
            $expected = $onLeaveAllWeek ? 0 : $competencies->count();
            $covered = count($currentRep);
            $sumCovered += $covered;
            $sumExpected += $expected;

            $rows[] = [
                'member' => $member,
                'overall' => $overall,
                'prevOverall' => $prevOverall,
                'categories' => $this->categoryAverages($currentRep, $competencies),
                'covered' => $covered,
                'expected' => $expected,
                'onLeave' => $onLeaveAllWeek,
                'declining' => $overall !== null && $prevOverall !== null && $overall < $prevOverall,
            ];
        }

        $rated = collect($rows)->filter(fn ($r) => $r['overall'] !== null);
        $leaderboard = collect($rows)->sortBy([
            fn ($a, $b) => ($a['overall'] === null) <=> ($b['overall'] === null),
            fn ($a, $b) => ($b['overall'] ?? 0) <=> ($a['overall'] ?? 0),
        ])->values();

        $needsAttention = collect($rows)->filter(
            fn ($r) => $r['overall'] !== null
                && ($r['overall'] < self::ATTENTION_THRESHOLD || $r['declining'])
        )->sortBy(fn ($r) => $r['overall'])->values();

        return [
            'week' => $week,
            'weekLabel' => PerformancePeriod::weekLabel($weekDate),
            'members' => $members,
            'rows' => $rows,
            'leaderboard' => $leaderboard,
            'teamAverage' => $rated->isNotEmpty() ? round($rated->avg('overall'), 2) : null,
            'topPerformer' => $leaderboard->first(fn ($r) => $r['overall'] !== null),
            'categoryAverages' => $this->teamCategoryAverages($rows),
            'coverage' => [
                'covered' => $sumCovered,
                'expected' => $sumExpected,
                'pct' => $sumExpected > 0 ? (int) round($sumCovered / $sumExpected * 100) : null,
                'unrated' => collect($rows)
                    ->filter(fn ($r) => ! $r['onLeave'] && $r['covered'] < $r['expected'])
                    ->map(fn ($r) => $r['member']->name)->values()->all(),
            ],
            'needsAttention' => $needsAttention,
            'trend' => $this->teamTrend($members, $scoresByUser, $weeks),
            'tasksheet' => $this->teamTasksheetPanel($team, $memberIds, $week),
        ];
    }

    // ---- shared computation -------------------------------------------------

    /** Active competencies that apply to a role, ordered for display. */
    private function applicableCompetencies(string $role): Collection
    {
        return PerformanceCompetency::active()->ordered()->get()
            ->filter(fn (PerformanceCompetency $c) => $c->appliesToRole($role))
            ->values();
    }

    /** Scores whose period_start falls inside a week window. */
    private function scoresInWeek(Collection $scores, array $week): Collection
    {
        return $scores->filter(
            fn (PerformanceScore $s) => $s->period_start->gte($week['start']) && $s->period_start->lte($week['end'])
        )->values();
    }

    /**
     * Per-competency representative score for a set of scores: the average of
     * that competency's scores (daily competencies collapse many days to one).
     *
     * @return array<int, float> competency_id => score
     */
    private function repScores(Collection $scores): array
    {
        return $scores->groupBy('competency_id')
            ->map(fn (Collection $g) => round($g->avg('score'), 2))
            ->all();
    }

    /**
     * Weight-weighted average over the competencies that actually have a
     * representative score. Returns null when none are rated (no divide-by-zero).
     *
     * @param  array<int, float>  $rep
     */
    private function weightedAverage(array $rep, Collection $competencies): ?float
    {
        $weighted = 0.0;
        $weight = 0;
        foreach ($competencies as $c) {
            if (! array_key_exists($c->id, $rep)) {
                continue;
            }
            $weighted += $rep[$c->id] * $c->weight;
            $weight += $c->weight;
        }

        return $weight > 0 ? round($weighted / $weight, 2) : null;
    }

    /**
     * Weighted average per category over rated competencies.
     *
     * @param  array<int, float>  $rep
     * @return array<string, array{label: string, value: float|null}>
     */
    private function categoryAverages(array $rep, Collection $competencies): array
    {
        $out = [];
        foreach (PerformanceCompetency::CATEGORIES as $key => $label) {
            $inCategory = $competencies->where('category', $key);
            $out[$key] = [
                'label' => $label,
                'value' => $inCategory->isEmpty() ? null : $this->weightedAverage($rep, $inCategory->values()),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{label: string, value: float|null}>
     */
    private function teamCategoryAverages(array $rows): array
    {
        $out = [];
        foreach (PerformanceCompetency::CATEGORIES as $key => $label) {
            $vals = collect($rows)
                ->map(fn ($r) => $r['categories'][$key]['value'] ?? null)
                ->filter(fn ($v) => $v !== null);
            $out[$key] = [
                'label' => $label,
                'value' => $vals->isNotEmpty() ? round($vals->avg(), 2) : null,
            ];
        }

        return $out;
    }

    /** @return array<int, array{label: string, value: float|null}> */
    private function teamTrend(Collection $members, Collection $scoresByUser, array $weeks): array
    {
        $trend = [];
        foreach ($weeks as $w) {
            $overalls = collect();
            foreach ($members as $member) {
                $competencies = $this->applicableCompetencies($member->role);
                $mine = $scoresByUser->get($member->id, collect());
                $overall = $this->weightedAverage($this->repScores($this->scoresInWeek($mine, $w)), $competencies);
                if ($overall !== null) {
                    $overalls->push($overall);
                }
            }
            $trend[] = [
                'label' => $w['label'],
                'value' => $overalls->isNotEmpty() ? round($overalls->avg(), 2) : null,
            ];
        }

        return $trend;
    }

    /**
     * Members who were on leave for the whole week (present zero days) — excluded
     * from evaluation-coverage expectations.
     *
     * @return array<int, bool> user_id => onLeaveAllWeek
     */
    private function weekLeaveByUser(Team $team, Collection $memberIds, array $week): array
    {
        if ($memberIds->isEmpty()) {
            return [];
        }

        $byUser = TasksheetEntry::where('team_id', $team->id)
            ->whereIn('user_id', $memberIds)
            ->whereBetween('date', [$week['start']->toDateString(), $week['end']->toDateString()])
            ->get()
            ->groupBy('user_id');

        $out = [];
        foreach ($byUser as $userId => $entries) {
            $present = $entries->filter(fn (TasksheetEntry $e) => ! $e->isOnLeave())->count();
            $leave = $entries->filter(fn (TasksheetEntry $e) => $e->isOnLeave())->count();
            $out[$userId] = $present === 0 && $leave > 0;
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function history(Collection $scores): array
    {
        return $scores
            ->sortByDesc(fn (PerformanceScore $s) => [$s->period_start->timestamp, $s->updated_at?->timestamp])
            ->take(15)
            ->map(fn (PerformanceScore $s) => [
                'competency' => $s->competency?->name ?? '—',
                'score' => $s->score,
                'label' => $s->scoreLabel(),
                'period' => $s->period_type === PerformanceCompetency::CADENCE_WEEKLY
                    ? 'Week of '.$s->period_start->format('M j')
                    : $s->period_start->format('M j, Y'),
                'evaluator' => $s->evaluator?->name,
                'note' => $s->note,
            ])->values()->all();
    }

    /**
     * Objective tasksheet panel for the member's week (never folded into score).
     *
     * @return array<string, mixed>
     */
    private function tasksheetPanel(User $member, Team $team, array $week): array
    {
        $entries = TasksheetEntry::where('team_id', $team->id)
            ->where('user_id', $member->id)
            ->whereBetween('date', [$week['start']->toDateString(), $week['end']->toDateString()])
            ->get();

        $worked = $entries->filter(fn (TasksheetEntry $e) => ! $e->isOnLeave());
        $onTime = $worked->filter(fn (TasksheetEntry $e) => ! $e->wasFilledLate())->count();

        // Per-day work-points trend across the week.
        $byDay = $entries->groupBy(fn (TasksheetEntry $e) => $e->date->toDateString());
        $daily = [];
        for ($cursor = $week['start']->copy(); $cursor->lte($week['end']); $cursor->addDay()) {
            $daily[] = [
                'label' => $cursor->format('D'),
                'value' => (int) ($byDay->get($cursor->toDateString())?->sum('work_points') ?? 0),
            ];
        }

        return [
            'workPoints' => (int) $entries->sum('work_points'),
            'ticketCount' => (int) $entries->sum('ticket_count'),
            'ticketPoints' => (int) $entries->sum('ticket_points'),
            'present' => $worked->count(),
            'leaveDays' => $entries->filter(fn (TasksheetEntry $e) => $e->isOnLeave())->count(),
            'onTimePct' => $worked->count() > 0 ? (int) round($onTime / $worked->count() * 100) : null,
            'daily' => $daily,
        ];
    }

    /**
     * Objective board panel — the member's current task record (a snapshot,
     * not week-bound: the board stores current status, not completion history).
     *
     * @return array<string, mixed>
     */
    private function boardPanel(User $member): array
    {
        $tasks = Task::where('assignee_id', $member->id)->get();
        $assigned = $tasks->count();
        $done = $tasks->whereIn('status', Task::DONE_STATUSES)->count();
        $rework = $tasks->where('status', 'recheck')->count();

        return [
            'assigned' => $assigned,
            'done' => $done,
            'open' => $assigned - $done,
            'donePct' => $assigned > 0 ? (int) round($done / $assigned * 100) : null,
            'rework' => $rework,
            'reworkPct' => $assigned > 0 ? (int) round($rework / $assigned * 100) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function teamTasksheetPanel(Team $team, Collection $memberIds, array $week): array
    {
        if ($memberIds->isEmpty()) {
            return ['workPoints' => 0, 'ticketCount' => 0, 'daily' => []];
        }

        $entries = TasksheetEntry::where('team_id', $team->id)
            ->whereIn('user_id', $memberIds)
            ->whereBetween('date', [$week['start']->toDateString(), $week['end']->toDateString()])
            ->get();

        $byDay = $entries->groupBy(fn (TasksheetEntry $e) => $e->date->toDateString());
        $daily = [];
        for ($cursor = $week['start']->copy(); $cursor->lte($week['end']); $cursor->addDay()) {
            $daily[] = [
                'label' => $cursor->format('D'),
                'value' => (int) ($byDay->get($cursor->toDateString())?->sum('work_points') ?? 0),
            ];
        }

        return [
            'workPoints' => (int) $entries->sum('work_points'),
            'ticketCount' => (int) $entries->sum('ticket_count'),
            'daily' => $daily,
        ];
    }
}

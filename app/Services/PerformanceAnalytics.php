<?php

namespace App\Services;

use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\Metric;
use App\Support\PerformancePeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes performance analytics for a member or a team over a weekly window.
 *
 * The blended headline score is the weight-weighted average of the member's
 * *rated* competencies — competencies without a rating this week are excluded
 * (renormalized), never treated as zero, so an incomplete evaluation never
 * drags the number down. Objective panels (tasksheet, board) are computed
 * separately and never fold into the rating score. Every average guards an
 * empty denominator and returns null rather than dividing by zero.
 *
 * The objective record (tasksheet habits, finished work, cycle time, rework,
 * overdue) sits beside the ratings as context over a rolling RECORD_WEEKS
 * window. Archived tasks are never counted. Everything here is lead-only:
 * the routes are behind `lead`, and team leads are scoped to their teams.
 */
class PerformanceAnalytics
{
    public function __construct(private readonly TaskCompletions $completions) {}

    private ?Collection $activeCompetencies = null;

    /** Weeks of objective record shown beside the ratings. */
    public const RECORD_WEEKS = 4;

    /** A competency averaging at least this is a strength; below it, room to grow. */
    public const STRENGTH_THRESHOLD = 3.5;

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

        $overall = $this->weightedAverage($currentRep, $competencies);
        $previousOverall = count($overallTrend) >= 2 ? $overallTrend[count($overallTrend) - 2]['value'] : null;
        [$strengths, $growth] = $this->strengthsAndGrowth($competencyRows);
        [$recordFrom, $recordTo] = $this->recordWindow($week);
        $span = $recordFrom->diffInDays($recordTo) + 1;

        return [
            'week' => $week,
            'weekLabel' => PerformancePeriod::weekLabel($weekDate),
            'overallChange' => Metric::compare($overall, $previousOverall),
            'strengths' => $strengths,
            'growthAreas' => $growth,
            'notes' => $this->notes($scores),
            'recordWindow' => ['start' => $recordFrom, 'end' => $recordTo, 'weeks' => self::RECORD_WEEKS],
            'record' => $this->compareRecords(
                $this->objectiveRecords($team, collect([$member->id]), $recordFrom, $recordTo)[$member->id],
                $this->objectiveRecords($team, collect([$member->id]), $recordFrom->copy()->subDays($span), $recordFrom->copy()->subSecond())[$member->id],
            ),
            'overall' => $overall,
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
        $teamAverage = $rated->isNotEmpty() ? round($rated->avg('overall'), 2) : null;
        $previousRated = collect($rows)->filter(fn ($r) => $r['prevOverall'] !== null);
        $previousAverage = $previousRated->isNotEmpty() ? round($previousRated->avg('prevOverall'), 2) : null;

        // Each member against the team average — a lead's comparison within
        // their own team, never shown outside the lead-only pages.
        foreach ($rows as $i => $row) {
            $rows[$i]['vsTeam'] = $row['overall'] !== null && $teamAverage !== null
                ? round($row['overall'] - $teamAverage, 2) : null;
        }

        [$recordFrom, $recordTo] = $this->recordWindow($week);
        $records = $this->objectiveRecords($team, $memberIds, $recordFrom, $recordTo);
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
            'teamAverage' => $teamAverage,
            'averageChange' => Metric::compare($teamAverage, $previousAverage),
            'competencyTrends' => $this->competencyTrends($members, $scoresByUser, $weeks),
            'distribution' => $this->distribution($scores, $week),
            'recordWindow' => ['start' => $recordFrom, 'end' => $recordTo, 'weeks' => self::RECORD_WEEKS],
            'records' => $records,
            'recordTotals' => $this->recordTotals($records),
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

    // ---- deeper analysis -----------------------------------------------------

    /**
     * The rolling window behind the objective record: RECORD_WEEKS weeks
     * ending with the selected week, never past today.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function recordWindow(array $week): array
    {
        $to = $week['end']->copy()->endOfDay()->min(Carbon::now());

        return [$week['start']->copy()->subWeeks(self::RECORD_WEEKS - 1)->startOfDay(), $to];
    }

    /**
     * Team average per competency per week, so a lead sees which skill is
     * moving rather than one blended line.
     *
     * @return list<array{competency: PerformanceCompetency, points: list<array{label: string, value: ?float}>, current: ?float, previous: ?float, change: ?float}>
     */
    private function competencyTrends(Collection $members, Collection $scoresByUser, array $weeks): array
    {
        $applicable = $this->activeCompetencies()
            ->filter(fn (PerformanceCompetency $c) => $members->contains(fn (User $m) => $c->appliesToRole($m->role)))
            ->values();

        // Representative scores per member per week, computed once.
        $repByWeek = [];
        foreach ($weeks as $i => $w) {
            foreach ($members as $member) {
                $repByWeek[$i][$member->id] = $this->repScores($this->scoresInWeek($scoresByUser->get($member->id, collect()), $w));
            }
        }

        return $applicable->map(function (PerformanceCompetency $c) use ($weeks, $repByWeek) {
            $points = [];
            foreach ($weeks as $i => $w) {
                $values = collect($repByWeek[$i] ?? [])->map(fn (array $rep) => $rep[$c->id] ?? null)->filter(fn ($v) => $v !== null);
                $points[] = ['label' => $w['label'], 'value' => $values->isNotEmpty() ? round($values->avg(), 2) : null];
            }
            $current = end($points)['value'];
            $previous = $points[count($points) - 2]['value'] ?? null;

            return [
                'competency' => $c,
                'points' => $points,
                ...Metric::compare($current, $previous),
            ];
        })->all();
    }

    /**
     * How the week's raw 1–5 ratings are spread.
     *
     * @return array{total: int, counts: array<int, int>, pct: array<int, ?int>}
     */
    private function distribution(Collection $scores, array $week): array
    {
        $inWeek = $this->scoresInWeek($scores, $week);
        $counts = [];
        foreach (array_keys(PerformanceScore::SCALE) as $value) {
            $counts[$value] = $inWeek->where('score', $value)->count();
        }

        return [
            'total' => $inWeek->count(),
            'counts' => $counts,
            'pct' => array_map(fn (int $n) => Metric::pct($n, $inWeek->count()), $counts),
        ];
    }

    /**
     * A member's competencies split by their own 8-week averages: what they
     * do well, and where the data suggests room to grow. Relative to the
     * member, never ranked against colleagues.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function strengthsAndGrowth(Collection $competencyRows): array
    {
        $averaged = $competencyRows->map(function (array $row) {
            $values = collect($row['trend'])->filter(fn ($v) => $v !== null)->values();
            if ($values->isEmpty()) {
                return null;
            }

            return [
                'competency' => $row['competency'],
                'average' => round($values->avg(), 2),
                'weeksRated' => $values->count(),
                // Direction across the window: last rated week against the first.
                'direction' => $values->count() >= 2 ? round($values->last() - $values->first(), 2) : null,
            ];
        })->filter()->values();

        return [
            $averaged->filter(fn ($c) => $c['average'] >= self::STRENGTH_THRESHOLD)->sortByDesc('average')->take(3)->values()->all(),
            $averaged->filter(fn ($c) => $c['average'] < self::STRENGTH_THRESHOLD)->sortBy('average')->take(3)->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function notes(Collection $scores): array
    {
        return $scores->filter(fn (PerformanceScore $s) => filled($s->note))
            ->sortByDesc(fn (PerformanceScore $s) => [$s->period_start->timestamp, $s->updated_at?->timestamp])
            ->take(10)
            ->map(fn (PerformanceScore $s) => [
                'competency' => $s->competency?->name ?? '—',
                'score' => $s->score,
                'period' => $s->period_type === PerformanceCompetency::CADENCE_WEEKLY
                    ? 'Week of '.$s->period_start->format('M j')
                    : $s->period_start->format('M j, Y'),
                'evaluator' => $s->evaluator?->name,
                'note' => $s->note,
            ])->values()->all();
    }

    /**
     * The objective record per member over a window: tasksheet habits on this
     * team, and the work they finished (tasks of this team or of no team).
     * A fixed number of queries for the whole team. Archived tasks never count.
     *
     * @return array<int, array<string, int|float|null>> user_id => record
     */
    private function objectiveRecords(Team $team, Collection $memberIds, Carbon $from, Carbon $to): array
    {
        $empty = fn () => [
            'daysLogged' => 0, 'daysExpected' => 0, 'tasksheetRate' => null,
            'standupAttended' => 0, 'standupMarked' => 0, 'standupRate' => null,
            'completed' => 0, 'onTimeRate' => null, 'cycleDays' => null, 'reworkRate' => null, 'overdue' => 0,
        ];
        $records = $memberIds->mapWithKeys(fn ($id) => [$id => $empty()])->all();
        if ($memberIds->isEmpty()) {
            return $records;
        }

        // Lead-only feedback is never read here.
        $entries = TasksheetEntry::query()
            ->where('team_id', $team->id)
            ->whereIn('user_id', $memberIds)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get(['user_id', 'date', ...TasksheetEntry::TASK_FIELDS, 'leave_type', 'standup_attended'])
            ->groupBy('user_id');

        $teamTasks = fn () => Task::query()
            ->whereIn('assignee_id', $memberIds)
            ->where(fn ($q) => $q->where('team_id', $team->id)->orWhereNull('team_id'));

        $done = $this->completions->since($teamTasks, $from)
            ->filter(fn (array $c) => $c['completedAt']->lte($to))
            ->groupBy('assigneeId');

        $overdue = $teamTasks()->counted()
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->whereDate('due_date', '<', today()->toDateString())
            ->selectRaw('assignee_id, count(*) as aggregate')
            ->groupBy('assignee_id')
            ->pluck('aggregate', 'assignee_id');

        // A member owes rows only from the day they joined this team.
        $joined = DB::table('team_user')
            ->where('team_id', $team->id)
            ->whereIn('user_id', $memberIds)
            ->pluck('created_at', 'user_id');

        $today = Carbon::today();
        foreach ($memberIds as $id) {
            $mine = $entries->get($id, collect())->keyBy(fn (TasksheetEntry $e) => $e->date->toDateString());

            // Weekdays owed a row: since joining, up to yesterday, plus today
            // once logged; full-day leave excluded.
            $start = $from->copy()->startOfDay()->max(isset($joined[$id]) ? Carbon::parse($joined[$id])->startOfDay() : $from);
            $expected = 0;
            $logged = 0;
            for ($d = $start; $d->lte($to); $d->addDay()) {
                $entry = $mine->get($d->toDateString());
                if ($d->isWeekend() || $entry?->isFullDayLeave() || ($d->gte($today) && ! $entry?->isFullyFilled())) {
                    continue;
                }
                $expected++;
                $logged += $entry?->isFullyFilled() ? 1 : 0;
            }

            $marked = $mine->filter(fn (TasksheetEntry $e) => $e->standup_attended !== null && ! $e->isFullDayLeave());
            $attended = $marked->filter(fn (TasksheetEntry $e) => $e->attendedStandup());

            $finished = $done->get($id, collect());
            $dated = $finished->filter(fn (array $c) => $c['dueDate'] !== null);

            $records[$id] = [
                'daysLogged' => $logged,
                'daysExpected' => $expected,
                'tasksheetRate' => Metric::pct($logged, $expected),
                'standupAttended' => $attended->count(),
                'standupMarked' => $marked->count(),
                'standupRate' => Metric::pct($attended->count(), $marked->count()),
                'completed' => $finished->count(),
                'onTimeRate' => Metric::pct(
                    $dated->filter(fn (array $c) => $c['completedAt']->toDateString() <= $c['dueDate']->toDateString())->count(),
                    $dated->count()
                ),
                'cycleDays' => TaskCompletions::avgCycleDays($finished->reject(fn (array $c) => $c['estimated'])),
                'reworkRate' => Metric::pct($finished->where('reworked', true)->count(), $finished->count()),
                'overdue' => (int) ($overdue[$id] ?? 0),
            ];
        }

        return $records;
    }

    /**
     * The team's record as one line: summed counts, and rates recomputed from
     * the summed parts (not an average of averages).
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, int|float|null>
     */
    private function recordTotals(array $records): array
    {
        $sum = fn (string $key) => (int) collect($records)->sum($key);
        $cycles = collect($records)->pluck('cycleDays')->filter(fn ($v) => $v !== null);

        return [
            'daysLogged' => $sum('daysLogged'),
            'daysExpected' => $sum('daysExpected'),
            'tasksheetRate' => Metric::pct($sum('daysLogged'), $sum('daysExpected')),
            'standupRate' => Metric::pct($sum('standupAttended'), $sum('standupMarked')),
            'completed' => $sum('completed'),
            'cycleDays' => $cycles->isNotEmpty() ? round($cycles->avg(), 1) : null,
            'overdue' => $sum('overdue'),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return array<string, array{current: int|float|null, previous: int|float|null, change: int|float|null}>
     */
    private function compareRecords(array $current, array $previous): array
    {
        return collect($current)->map(fn ($value, $key) => Metric::compare($value, $previous[$key] ?? null))->all();
    }

    // ---- shared computation -------------------------------------------------

    /** Active competencies that apply to a role, ordered for display. */
    private function applicableCompetencies(string $role): Collection
    {
        return $this->activeCompetencies()
            ->filter(fn (PerformanceCompetency $c) => $c->appliesToRole($role))
            ->values();
    }

    /**
     * The active catalog, read once per request — it was re-queried for every
     * member and every charted week.
     */
    private function activeCompetencies(): Collection
    {
        return $this->activeCompetencies ??= PerformanceCompetency::active()->ordered()->get();
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
        // Archived tasks are filed away and left out of the record entirely.
        $tasks = Task::counted()->where('assignee_id', $member->id)->get();
        $assigned = $tasks->count();
        $done = $tasks->where('status', 'done')->count();
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

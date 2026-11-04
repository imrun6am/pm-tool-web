<?php

namespace App\Services;

use App\Models\Release;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How a release is going: scope against finished work over time, and when it's
 * likely to finish given how fast its team has actually been finishing tasks.
 */
class ReleaseProgressService
{
    /** Weeks of team history a forecast samples from. */
    public const HISTORY_WEEKS = 12;

    /** A forecast needs at least this many weeks with something finished. */
    private const MIN_ACTIVE_WEEKS = 3;

    private const RUNS = 2000;

    /** Weeks after which a simulated run is treated as never finishing. */
    private const MAX_WEEKS = 104;

    /** Points on the chart, so a long release stays readable. */
    private const MAX_POINTS = 24;

    public function __construct(private readonly TaskCompletions $completions) {}

    /**
     * Total scope and finished work at each point from the release's start.
     * Scope counts top-level tasks that existed by then, so tasks added later
     * show as the line rising. Archived and trashed tasks aren't counted.
     *
     * @return array<int, array{date: Carbon, scope: int, done: int}>
     */
    public function burnUp(Release $release): array
    {
        $tasks = $release->tasks()->counted()->whereNull('parent_id')->with('statusChanges')->get();

        $last = ($release->completed_at ?? today())->copy()->startOfDay()->min($release->end_date->copy()->max(today()));
        $first = $release->start_date->copy()->startOfDay();
        if ($last->lt($first)) {
            $last = $first->copy();
        }

        $days = (int) $first->diffInDays($last);
        $step = max(1, (int) ceil($days / (self::MAX_POINTS - 1)));

        $points = [];
        for ($offset = 0; $offset <= $days; $offset += $step) {
            $points[] = $this->pointAt($tasks, $first->copy()->addDays($offset));
        }
        if ($days % $step !== 0) {
            $points[] = $this->pointAt($tasks, $last);
        }

        return $points;
    }

    /**
     * When the open work is likely to finish, by replaying the team's own weekly
     * throughput many times. Null `dates` means it can't be said: there's nothing
     * left, or too little history to go on.
     *
     * @return array{remaining: int, enough_history: bool, dates: ?array{p50: Carbon, p85: Carbon, p95: Carbon}, on_time_pct: ?int, weeks_sampled: int, avg_per_week: float}
     */
    public function forecast(Release $release): array
    {
        $remaining = $release->tasks()->counted()->whereNull('parent_id')->where('status', '!=', 'done')->count();
        $weekly = $this->weeklyThroughput($release->team_id);
        $active = count(array_filter($weekly));

        $base = [
            'remaining' => $remaining,
            'enough_history' => $active >= self::MIN_ACTIVE_WEEKS,
            'dates' => null,
            'on_time_pct' => null,
            'weeks_sampled' => count($weekly),
            'avg_per_week' => round(array_sum($weekly) / max(1, count($weekly)), 1),
        ];

        if ($release->completed_at || $remaining === 0 || ! $base['enough_history']) {
            return $base;
        }

        $runs = $this->simulate($remaining, $weekly, $release->id);
        $weeksLeft = (int) floor(today()->diffInDays($release->end_date, false) / 7);

        return [
            'dates' => [
                'p50' => today()->addWeeks($this->percentile($runs, 50)),
                'p85' => today()->addWeeks($this->percentile($runs, 85)),
                'p95' => today()->addWeeks($this->percentile($runs, 95)),
            ],
            'on_time_pct' => $weeksLeft < 0 ? 0 : (int) round(count(array_filter($runs, fn (int $w) => $w <= $weeksLeft)) / count($runs) * 100),
        ] + $base;
    }

    /** @return array{date: Carbon, scope: int, done: int} */
    private function pointAt(Collection $tasks, Carbon $day): array
    {
        $end = $day->copy()->endOfDay();
        $scope = 0;
        $done = 0;

        foreach ($tasks as $task) {
            if ($task->created_at->gt($end)) {
                continue;
            }

            $scope++;
            $status = $task->statusChanges->filter(fn ($c) => $c->changed_at->lte($end))->last()?->to_status;

            // Old rows may have no history: a finished one counts from its last save.
            if ($task->statusChanges->isEmpty() && $task->status === 'done') {
                $status = $task->updated_at->lte($end) ? 'done' : null;
            }

            if ($status === 'done') {
                $done++;
            }
        }

        return ['date' => $day, 'scope' => $scope, 'done' => $done];
    }

    /** @return array<int, int> finished tasks per full week, oldest first, ending last week, from the first week with any */
    private function weeklyThroughput(?int $teamId): array
    {
        $thisWeek = today()->startOfWeek();
        $from = $thisWeek->copy()->subWeeks(self::HISTORY_WEEKS);

        $finished = $this->completions->since(
            fn () => Task::query()->counted()->whereNull('parent_id')->where('team_id', $teamId),
            $from,
        );

        $weeks = array_fill(0, self::HISTORY_WEEKS, 0);
        foreach ($finished as $completion) {
            if ($completion['completedAt']->lt($thisWeek)) {
                $weeks[(int) $from->diffInWeeks($completion['completedAt'])]++;
            }
        }

        // Weeks before the team's first finished task say nothing about its pace.
        $first = array_key_first(array_filter($weeks));

        return $first === null ? [] : array_slice($weeks, $first);
    }

    /**
     * The seed comes from the release, so a page shows the same dates until the
     * facts change, and each run's weeks are drawn from real past weeks.
     *
     * @param  array<int, int>  $weekly
     * @return array<int, int> weeks needed, per run
     */
    private function simulate(int $remaining, array $weekly, int $seed): array
    {
        mt_srand($seed);

        $runs = [];
        $last = count($weekly) - 1;
        for ($run = 0; $run < self::RUNS; $run++) {
            $left = $remaining;
            $weeks = 0;
            while ($left > 0 && $weeks < self::MAX_WEEKS) {
                $left -= $weekly[mt_rand(0, $last)];
                $weeks++;
            }
            $runs[] = $weeks;
        }

        mt_srand();
        sort($runs);

        return $runs;
    }

    /** @param  array<int, int>  $sorted */
    private function percentile(array $sorted, int $pct): int
    {
        return $sorted[(int) min(count($sorted) - 1, ceil(count($sorted) * $pct / 100) - 1)];
    }
}

<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How work moves through a team: what sits in each status over time, what comes
 * in against what gets finished, and how long finishing takes. Team-level only:
 * nothing here names who did the work, so it can't turn into a leaderboard.
 */
class FlowReportService
{
    public const WEEKS = 12;

    /** Statuses as stacked on the flow chart. Archive is filed away, so it's left out. */
    public const BANDS = ['todo', 'in_progress', 'in_review', 'recheck', 'done'];

    private const AGING_LIMIT = 8;

    /** Dots on the cycle time chart. The percentiles still use everything. */
    private const CYCLE_POINTS = 300;

    public function __construct(private readonly TaskCompletions $completions) {}

    /** @return array<string, mixed> */
    public function report(?int $teamId = null): array
    {
        $weeks = $this->weeks();
        $start = $weeks[0]['start'];

        $scope = fn () => Task::query()->counted()->whereNull('parent_id')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId));

        // Work finished before the window would only pad the Done band.
        $tasks = $scope()
            ->where(fn ($q) => $q->where('status', '!=', 'done')->orWhere('updated_at', '>=', $start))
            ->with('statusChanges')
            ->get();

        $finished = $this->completions->since($scope, $start);
        $cycle = $this->cycle($finished, $tasks);
        $days = $cycle->pluck('days')->all();
        $p85 = $this->percentile($days, 85);

        return [
            'weeks' => array_map(fn (array $w) => ['start' => $w['start'], 'end' => $w['end'], 'current' => $w['current']], $weeks),
            'flow' => array_map(fn (array $w) => $this->countsAt($tasks, $w['snapshot']), $weeks),
            'arrivals' => array_map(fn (array $w) => $tasks->filter(fn (Task $t) => $t->created_at->betweenIncluded($w['start'], $w['end']))->count(), $weeks),
            'completions' => array_map(fn (array $w) => $finished->filter(fn (array $c) => $c['completedAt']->betweenIncluded($w['start'], $w['end']))->count(), $weeks),
            'cycle' => $cycle->take(-self::CYCLE_POINTS)->values()->all(),
            'cycle_p50' => $this->percentile($days, 50),
            'cycle_p85' => $p85,
            'finished' => $finished->count(),
            'in_progress' => $tasks->whereIn('status', ['in_progress', 'in_review', 'recheck'])->count(),
            'aging' => $this->aging($tasks, $p85),
        ];
    }

    /** @return array<int, array{start: Carbon, end: Carbon, snapshot: Carbon, current: bool}> */
    private function weeks(): array
    {
        $thisWeek = today()->startOfWeek();

        return collect(range(self::WEEKS - 1, 0))->map(function (int $ago) use ($thisWeek) {
            $start = $thisWeek->copy()->subWeeks($ago);
            $end = $start->copy()->endOfWeek();

            return ['start' => $start, 'end' => $end, 'snapshot' => $end->copy()->min(now()), 'current' => $ago === 0];
        })->all();
    }

    /** @return array<string, int> */
    private function countsAt(Collection $tasks, Carbon $at): array
    {
        $counts = array_fill_keys(self::BANDS, 0);

        foreach ($tasks as $task) {
            if ($task->created_at->gt($at)) {
                continue;
            }

            // Old rows may have no history: they count as they are now, from their last save.
            $status = $task->statusChanges->isEmpty()
                ? ($task->status === 'done' && $task->updated_at->gt($at) ? 'todo' : $task->status)
                : $task->statusChanges->filter(fn ($c) => $c->changed_at->lte($at))->last()?->to_status;

            if ($status !== null && isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    /** @return Collection<int, array{task_id: int, key: string, title: string, completed_at: Carbon, days: float}> */
    private function cycle(Collection $finished, Collection $tasks): Collection
    {
        $byId = $tasks->keyBy('id');

        return $finished
            ->reject(fn (array $c) => $c['estimated'] || ! $c['startedAt'] || ! $byId->has($c['taskId']))
            ->map(fn (array $c) => [
                'task_id' => $c['taskId'],
                'key' => $byId[$c['taskId']]->reference(),
                'title' => $byId[$c['taskId']]->title,
                'completed_at' => $c['completedAt'],
                'days' => round($c['startedAt']->diffInSeconds($c['completedAt']) / 86400, 1),
            ])
            ->sortBy(fn (array $c) => $c['completed_at']->getTimestamp())
            ->values();
    }

    /**
     * Work in progress that has already taken longer than most finished work
     * did, oldest first. Worth a look while it can still be helped.
     *
     * @return array<int, array{task_id: int, key: string, title: string, status: string, days: int}>
     */
    private function aging(Collection $tasks, ?float $p85): array
    {
        if ($p85 === null) {
            return [];
        }

        return $tasks->whereIn('status', ['in_progress', 'in_review', 'recheck'])
            ->map(function (Task $task) {
                $started = $task->statusChanges->firstWhere('to_status', 'in_progress')?->changed_at ?? $task->created_at;

                return ['task_id' => $task->id, 'key' => $task->reference(), 'title' => $task->title, 'status' => $task->statusLabel(), 'days' => (int) $started->copy()->startOfDay()->diffInDays(today())];
            })
            ->filter(fn (array $row) => $row['days'] > $p85)
            ->sortByDesc('days')
            ->take(self::AGING_LIMIT)
            ->values()
            ->all();
    }

    /** @param  array<int, float>  $values */
    private function percentile(array $values, int $pct): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);

        return (float) $values[(int) min(count($values) - 1, ceil(count($values) * $pct / 100) - 1)];
    }
}

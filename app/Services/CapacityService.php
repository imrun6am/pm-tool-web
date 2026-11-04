<?php

namespace App\Services;

use App\Models\ReleaseOffDay;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\WorkingWeek;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who has room when: each team member's available time per week against the
 * work due that week.
 *
 * Available time is working days, less weekends, the team's release off-days
 * and recorded leave. This week counts only the days left. Load is what's left
 * of each open task's estimate (the estimate less time logged on it), in the
 * week it's due; overdue work counts this week, and tasks with no due date or
 * one past the window go in `later`. A task with no estimate can't be counted,
 * so it's reported as `unestimated`, because a person can look free when their
 * work just isn't estimated yet.
 */
class CapacityService
{
    /**
     * The teams `$viewer` may plan: every team for org-level leads, their own
     * for a team lead.
     *
     * @return Collection<int, Team>
     */
    public function teamsFor(User $viewer): Collection
    {
        return Team::query()
            ->unless($viewer->canOverseeAllTeams(), fn ($q) => $q->where('team_lead_id', $viewer->id))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{weeks: array<int, array{start: Carbon, end: Carbon, current: bool}>, people: array<int, array<string, mixed>>}
     */
    public function forTeam(Team $team, ?Carbon $from = null): array
    {
        $weeks = $this->weeks($from);
        $people = $team->members()->active()->orderBy('name')->get();

        $start = $weeks[0]['start'];
        $end = $weeks[array_key_last($weeks)]['end'];
        $offDays = $this->offDays($team, $start, $end);
        $leave = $this->leave($people, $start, $end);
        $tasks = $this->openTasks($people)->groupBy('assignee_id');

        return [
            'weeks' => $weeks,
            'people' => $people->map(fn (User $person) => $this->row(
                $person,
                $weeks,
                $tasks->get($person->id, collect()),
                $offDays,
                $leave->get($person->id, collect()),
            ))->all(),
        ];
    }

    /** The week a date falls in; never before this week, since it's for planning ahead. */
    public function firstWeek(?Carbon $from = null): Carbon
    {
        $thisWeek = today()->startOfWeek();
        $week = ($from ?? $thisWeek)->copy()->startOfWeek();

        return $week->lt($thisWeek) ? $thisWeek : $week;
    }

    /** @return array<int, array{start: Carbon, end: Carbon, current: bool}> */
    private function weeks(?Carbon $from): array
    {
        $start = $this->firstWeek($from);

        return collect(range(0, config('planning.capacity_weeks') - 1))->map(function (int $i) use ($start) {
            $weekStart = $start->copy()->addWeeks($i);

            return ['start' => $weekStart, 'end' => $weekStart->copy()->endOfWeek(), 'current' => today()->betweenIncluded($weekStart, $weekStart->copy()->endOfWeek())];
        })->all();
    }

    /** @return Collection<int, Task> */
    private function openTasks(Collection $people): Collection
    {
        return Task::query()
            ->whereIn('assignee_id', $people->pluck('id'))
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->withSum('timeLogs as logged_minutes', 'minutes')
            ->get(['id', 'assignee_id', 'due_date', 'estimate_minutes']);
    }

    /** @return array<string, true> dates the team's ongoing releases mark as off */
    private function offDays(Team $team, Carbon $start, Carbon $end): array
    {
        return ReleaseOffDay::query()
            ->whereHas('release', fn ($q) => $q->ongoing()->where('team_id', $team->id))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [Carbon::parse($date)->toDateString() => true])
            ->all();
    }

    /** @return Collection<int, Collection<string, float>> days away by person, then by date: 1 or 0.5 */
    private function leave(Collection $people, Carbon $start, Carbon $end): Collection
    {
        return TasksheetEntry::query()
            ->whereIn('user_id', $people->pluck('id'))
            ->whereNotNull('leave_type')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['user_id', 'date', 'leave_type'])
            ->groupBy('user_id')
            ->map(fn (Collection $entries) => $entries->mapWithKeys(fn (TasksheetEntry $e) => [
                $e->date->toDateString() => in_array($e->leave_type, TasksheetEntry::FULL_DAY_LEAVE_TYPES, true) ? 1.0 : 0.5,
            ]));
    }

    /** @return array<string, mixed> */
    private function row(User $person, array $weeks, Collection $tasks, array $offDays, Collection $leave): array
    {
        $empty = fn () => ['load_minutes' => 0, 'tasks' => 0, 'unestimated' => 0];
        $cells = array_map(fn () => $empty(), $weeks);
        $later = $empty();

        foreach ($tasks as $task) {
            $index = $this->weekIndex($task, $weeks);
            $remaining = max(0, (int) $task->estimate_minutes - (int) $task->logged_minutes);

            if ($index === null) {
                $later['tasks']++;
                $later['load_minutes'] += $remaining;
                $later['unestimated'] += $task->estimate_minutes ? 0 : 1;
            } else {
                $cells[$index]['tasks']++;
                $cells[$index]['load_minutes'] += $remaining;
                $cells[$index]['unestimated'] += $task->estimate_minutes ? 0 : 1;
            }
        }

        foreach ($weeks as $i => $week) {
            $available = $this->availableMinutes($week, $offDays, $leave);
            $cells[$i] += ['available_minutes' => $available, 'over' => $cells[$i]['load_minutes'] > $available];
        }

        return ['user' => $person, 'cells' => $cells, 'later' => $later];
    }

    /** Which week the task counts in: overdue is this week, and null means later or undated. */
    private function weekIndex(Task $task, array $weeks): ?int
    {
        if (! $task->due_date) {
            return null;
        }

        foreach ($weeks as $i => $week) {
            if ($task->due_date->lte($week['end'])) {
                return $i;
            }
        }

        return null;
    }

    private function availableMinutes(array $week, array $offDays, Collection $leave): int
    {
        $from = $week['start']->max(today());
        $days = 0.0;

        for ($day = $from->copy(); $day->lte($week['end']); $day->addDay()) {
            if (WorkingWeek::isWeekend($day) || isset($offDays[$day->toDateString()])) {
                continue;
            }

            $days += 1 - $leave->get($day->toDateString(), 0);
        }

        return (int) round($days * config('planning.hours_per_day') * 60);
    }
}

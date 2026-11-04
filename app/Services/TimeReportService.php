<?php

namespace App\Services;

use App\Models\Release;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Where the logged hours went, and how they compare with what was estimated.
 *
 * For planning, not for judging people: nothing here feeds performance scores
 * or KPI reports. An org-level lead sees every team, a team lead their own.
 */
class TimeReportService
{
    private const TOP_MISSES = 8;

    /**
     * @param  array{from: Carbon, to: Carbon, team_id: ?int, release_id: ?int}  $filters
     * @return array<string, mixed>
     */
    public function report(array $filters, User $viewer): array
    {
        $logs = fn () => $this->logs($filters, $viewer);

        return [
            'total_minutes' => (int) $logs()->sum('task_time_logs.minutes'),
            'by_team' => $this->named($logs(), 'tasks.team_id', Team::class, 'No team'),
            'by_release' => $this->named($logs(), 'tasks.release_id', Release::class, 'No release'),
            'by_person' => $this->named($logs(), 'task_time_logs.user_id', User::class, 'Former user'),
            'accuracy' => $this->accuracy($filters, $viewer),
        ];
    }

    /**
     * Every entry, oldest first, for a spreadsheet.
     *
     * @param  array{from: Carbon, to: Carbon, team_id: ?int, release_id: ?int}  $filters
     * @return Builder<TaskTimeLog>
     */
    public function entries(array $filters, User $viewer): Builder
    {
        return $this->logs($filters, $viewer)
            ->with(['user', 'task.team', 'task.release'])
            ->select('task_time_logs.*')
            ->orderBy('task_time_logs.logged_on')
            ->orderBy('task_time_logs.id');
    }

    /** @return Builder<TaskTimeLog> */
    private function logs(array $filters, User $viewer): Builder
    {
        return TaskTimeLog::query()
            ->join('tasks', 'tasks.id', '=', 'task_time_logs.task_id')
            ->whereNull('tasks.deleted_at')
            ->whereBetween('task_time_logs.logged_on', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->when($filters['team_id'] ?? null, fn ($q, $id) => $q->where('tasks.team_id', $id))
            ->when($filters['release_id'] ?? null, fn ($q, $id) => $q->where('tasks.release_id', $id))
            ->unless($viewer->canOverseeAllTeams(), fn ($q) => $q->whereIn('tasks.team_id', Team::where('team_lead_id', $viewer->id)->select('id')));
    }

    /**
     * Minutes grouped by a column, biggest first, with names looked up after.
     *
     * @param  class-string  $model
     * @return array<int, array{id: ?int, name: string, minutes: int}>
     */
    private function named(Builder $query, string $column, string $model, string $none): array
    {
        $rows = $query->selectRaw("{$column} as group_id, sum(task_time_logs.minutes) as minutes")
            ->groupBy($column)
            ->orderByDesc('minutes')
            ->get();

        $names = (in_array(SoftDeletes::class, class_uses_recursive($model), true) ? $model::withTrashed() : $model::query())
            ->whereKey($rows->pluck('group_id')->filter())
            ->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'id' => $row->group_id ? (int) $row->group_id : null,
            'name' => $row->group_id ? ($names[$row->group_id] ?? $none) : $none,
            'minutes' => (int) $row->minutes,
        ])->all();
    }

    /**
     * Estimates against logged time for tasks finished in the period that have
     * both. Parents and subtasks each count on their own.
     *
     * @return array{tasks: int, estimated_minutes: int, logged_minutes: int, ratio: ?float, misses: array<int, array<string, mixed>>}
     */
    private function accuracy(array $filters, User $viewer): array
    {
        $tasks = Task::query()
            ->where('status', 'done')
            ->whereNotNull('estimate_minutes')
            ->whereHas('statusChanges', fn ($q) => $q->where('to_status', 'done')
                ->whereBetween('changed_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()]))
            ->when($filters['team_id'] ?? null, fn ($q, $id) => $q->where('team_id', $id))
            ->when($filters['release_id'] ?? null, fn ($q, $id) => $q->where('release_id', $id))
            ->unless($viewer->canOverseeAllTeams(), fn ($q) => $q->whereIn('team_id', Team::where('team_lead_id', $viewer->id)->select('id')))
            ->withSum('timeLogs as logged_minutes', 'minutes')
            ->get()
            ->filter(fn (Task $task) => (int) $task->logged_minutes > 0);

        $estimated = (int) $tasks->sum('estimate_minutes');
        $logged = (int) $tasks->sum(fn (Task $task) => (int) $task->logged_minutes);

        return [
            'tasks' => $tasks->count(),
            'estimated_minutes' => $estimated,
            'logged_minutes' => $logged,
            'ratio' => $estimated > 0 ? round($logged / $estimated, 2) : null,
            'misses' => $this->misses($tasks),
        ];
    }

    /** @return array<int, array<string, mixed>> the tasks furthest from their estimate, either way */
    private function misses(Collection $tasks): array
    {
        return $tasks
            ->sortByDesc(fn (Task $task) => abs((int) $task->logged_minutes - $task->estimate_minutes))
            ->take(self::TOP_MISSES)
            ->map(fn (Task $task) => [
                'task_id' => $task->id,
                'key' => $task->reference(),
                'title' => $task->title,
                'estimated_minutes' => $task->estimate_minutes,
                'logged_minutes' => (int) $task->logged_minutes,
            ])
            ->values()
            ->all();
    }
}

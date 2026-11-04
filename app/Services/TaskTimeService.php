<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Hours logged on tasks. A task's totals include its subtasks, so a parent
 * shows the whole piece of work.
 */
class TaskTimeService
{
    /** The most entries one task lists; its totals always cover everything. */
    public const ENTRY_LIMIT = 200;

    /** @param  array{minutes: int, logged_on: string, note?: ?string}  $data */
    public function log(Task $task, User $user, array $data): TaskTimeLog
    {
        return $task->timeLogs()->create($data + ['user_id' => $user->id])->setRelation('task', $task);
    }

    /** @param  array{minutes: int, logged_on: string, note?: ?string}  $data */
    public function update(TaskTimeLog $log, array $data): TaskTimeLog
    {
        $log->update($data);

        return $log;
    }

    public function delete(TaskTimeLog $log): void
    {
        $log->delete();
    }

    /**
     * Time spent and estimated. `estimate_minutes` adds the subtasks' estimates
     * to the task's own, and is null when nothing has one.
     *
     * @return array{total_minutes: int, own_minutes: int, subtask_minutes: int, estimate_minutes: ?int}
     */
    public function summary(Task $task): array
    {
        $own = (int) TaskTimeLog::where('task_id', $task->id)->sum('minutes');
        $subtasks = (int) TaskTimeLog::whereIn('task_id', $task->subtasks()->reorder()->select('id'))->sum('minutes');
        $estimates = $task->subtasks()->reorder()->whereNotNull('estimate_minutes')->pluck('estimate_minutes')
            ->when($task->estimate_minutes, fn ($list, $estimate) => $list->push($estimate));

        return [
            'total_minutes' => $own + $subtasks,
            'own_minutes' => $own,
            'subtask_minutes' => $subtasks,
            'estimate_minutes' => $estimates->isEmpty() ? null : (int) $estimates->sum(),
        ];
    }

    /**
     * The task's and its subtasks' entries, newest first.
     *
     * @return Collection<int, TaskTimeLog>
     */
    public function entries(Task $task): Collection
    {
        return TaskTimeLog::query()
            ->where(fn ($q) => $q->where('task_id', $task->id)
                ->orWhereIn('task_id', $task->subtasks()->reorder()->select('id')))
            ->with(['user', 'task'])
            ->orderByDesc('logged_on')
            ->orderByDesc('id')
            ->limit(self::ENTRY_LIMIT)
            ->get();
    }
}

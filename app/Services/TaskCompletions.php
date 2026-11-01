<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * When work was finished, read from the RecordsActivity audit trail — tasks
 * carry no started/completed columns of their own.
 *
 *  - completed: the last move into Done from an open status (Archive → Done
 *    is a restore, not a completion). Only tasks currently Done count, so
 *    reopened and archived work drops out.
 *  - started:   the first move to In Progress.
 *  - reworked:  the task was ever sent to Recheck.
 *
 * A Done task with no logged transition (seeded rows, or created already
 * done) falls back to `updated_at` and is flagged `estimated`; callers use
 * those for counts but never for durations.
 *
 * Two queries regardless of history: the candidate tasks, then their status
 * changes. Candidates are bounded by `updated_at >= $from`, since a task
 * completed in the window was necessarily saved in it.
 */
class TaskCompletions
{
    /**
     * @param  \Closure(): Builder  $tasks  a fresh, scoped Task query per call
     * @return Collection<int, array{taskId: int, assigneeId: ?int, completedAt: Carbon, startedAt: ?Carbon, reworked: bool, estimated: bool, dueDate: ?Carbon, createdAt: Carbon}>
     */
    public function since(\Closure $tasks, Carbon $from): Collection
    {
        $done = fn (): Builder => $tasks()->where('status', 'done')->where('updated_at', '>=', $from);

        $candidates = $done()->get(['id', 'assignee_id', 'due_date', 'created_at', 'updated_at']);

        $history = Activity::query()
            ->where('subject_type', (new Task)->getMorphClass())
            ->whereIn('subject_id', $done()->select('id'))
            ->whereIn('event', ['created', 'updated'])
            ->whereNotNull('properties->attributes->status')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'subject_id', 'event', 'properties', 'created_at'])
            ->groupBy('subject_id');

        return $candidates->map(function (Task $task) use ($history) {
            $startedAt = $completedAt = null;
            $reworked = false;

            foreach ($history->get($task->id, []) as $activity) {
                $new = $activity->properties['attributes']['status'] ?? null;
                $old = $activity->event === 'created' ? null : ($activity->properties['old']['status'] ?? null);

                if ($new === 'in_progress' && $startedAt === null) {
                    $startedAt = $activity->created_at;
                }
                if ($new === 'recheck') {
                    $reworked = true;
                }
                if ($new === 'done' && ! in_array($old, Task::DONE_STATUSES, true)) {
                    $completedAt = $activity->created_at;
                }
            }

            return [
                'taskId' => $task->id,
                'assigneeId' => $task->assignee_id,
                'completedAt' => $completedAt ?? $task->updated_at,
                'startedAt' => $startedAt && $completedAt && $startedAt->lte($completedAt) ? $startedAt : null,
                'reworked' => $reworked,
                'estimated' => $completedAt === null,
                'dueDate' => $task->due_date,
                'createdAt' => $task->created_at,
            ];
        })->filter(fn (array $c) => $c['completedAt']->gte($from))->values();
    }

    /** Average days from start to completion, or null with nothing measurable. */
    public static function avgCycleDays(Collection $completions): ?float
    {
        $cycles = $completions->filter(fn (array $c) => $c['startedAt'] !== null)
            ->map(fn (array $c) => $c['startedAt']->diffInSeconds($c['completedAt']) / 86400);

        return $cycles->isEmpty() ? null : round($cycles->avg(), 1);
    }
}

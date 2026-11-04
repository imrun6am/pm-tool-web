<?php

namespace App\Services;

use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\User;
use App\Support\WorkingWeek;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Creates tasks from recurring rules. A rule missed for a while, say while the
 * scheduler was down, makes one task and moves on, not one for every missed day.
 */
class RecurringTaskService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly NotificationService $notifications,
    ) {}

    /** @return Collection<int, RecurringTask> */
    public function all(): Collection
    {
        return RecurringTask::with(['team', 'assignee'])->orderByDesc('is_active')->orderBy('next_run_on')->orderBy('id')->get();
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $author): RecurringTask
    {
        return RecurringTask::create($data + [
            'created_by' => $author->id,
            'anchor_day' => $data['frequency'] === 'monthly' ? Carbon::parse($data['next_run_on'])->day : null,
        ]);
    }

    public function setActive(RecurringTask $rule, bool $active): RecurringTask
    {
        // Coming back from a pause shouldn't create work for the time it was off.
        $changes = ['is_active' => $active];
        if ($active && $rule->next_run_on->lt(today())) {
            $changes['next_run_on'] = $this->nextAfter($rule, today()->subDay());
        }

        $rule->update($changes);

        return $rule;
    }

    public function delete(RecurringTask $rule): void
    {
        $rule->delete();
    }

    /** @return int how many tasks were created */
    public function runDue(?Carbon $today = null): int
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $created = 0;
        $tasks = [];

        try {
            $this->createDue($today, $created, $tasks);
        } finally {
            // Whatever made it before a failure still reaches its assignee. A
            // signed-in caller's writes have told them already, through the observer.
            if (! Auth::check()) {
                $this->notifications->recurringTasksCreated($tasks);
            }
        }

        return $created;
    }

    /** @param  list<Task>  $tasks  filled with what was created */
    private function createDue(Carbon $today, int &$created, array &$tasks): void
    {
        RecurringTask::query()
            ->where('is_active', true)
            ->whereDate('next_run_on', '<=', $today->toDateString())
            ->with(['creator', 'assignee'])
            ->orderBy('id')
            ->each(function (RecurringTask $rule) use ($today, &$created, &$tasks) {
                DB::transaction(function () use ($rule, $today, &$created, &$tasks) {
                    if ($rule->creator) {
                        $tasks[] = $this->tasks->create([
                            'title' => $rule->title,
                            'description' => $rule->description,
                            'team_id' => $rule->team_id,
                            // Someone who has left can't be given new work.
                            'assignee_id' => $rule->assignee?->isActive() && ! $rule->assignee->trashed() ? $rule->assignee_id : null,
                            'priority' => $rule->priority,
                            'estimate_minutes' => $rule->estimate_minutes,
                            'due_date' => $today->copy()->addDays($rule->due_in_days)->toDateString(),
                        ], $rule->creator);
                        $created++;
                    }

                    $rule->update(['last_run_on' => $today, 'next_run_on' => $this->nextAfter($rule, $today)]);
                });
            });
    }

    /** The first run date after `$after`. */
    public function nextAfter(RecurringTask $rule, Carbon $after): Carbon
    {
        $next = $rule->next_run_on->copy()->startOfDay();

        do {
            $next = match ($rule->frequency) {
                'daily' => $this->nextWorkingDay($next),
                'weekly' => $next->addWeek(),
                default => $this->nextMonth($next, $rule->anchor_day ?? $next->day),
            };
        } while ($next->lte($after));

        return $next;
    }

    private function nextWorkingDay(Carbon $from): Carbon
    {
        return WorkingWeek::next($from);
    }

    /** The same day next month, or its last day if that month is shorter. */
    private function nextMonth(Carbon $from, int $day): Carbon
    {
        $month = $from->copy()->startOfMonth()->addMonth();

        return $month->day(min($day, $month->daysInMonth));
    }
}

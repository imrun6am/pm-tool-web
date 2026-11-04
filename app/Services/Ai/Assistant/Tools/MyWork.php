<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\Event;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;

class MyWork extends ReadTool
{
    public function name(): string
    {
        return 'my_work';
    }

    public function description(): string
    {
        return "This person's day at a glance: their open tasks (overdue first), today's tasksheet rows, and their events for the next 7 days.";
    }

    public function status(): string
    {
        return 'Checking your work';
    }

    public function properties(): array
    {
        return [];
    }

    public function run(array $input, User $user): ToolOutput
    {
        $tasks = Task::visibleTo($user)->with(['assignee', 'release', 'team'])
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date')
            ->limit(30)->get();

        $events = Event::visibleTo($user)
            ->where(fn ($q) => $q->where('created_by', $user->id)->orWhereHas('attendees', fn ($a) => $a->whereKey($user->id)))
            ->whereBetween('starts_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->orderBy('starts_at')->limit(15)->get();

        $rows = TasksheetEntry::with('team:id,name')->where('user_id', $user->id)
            ->whereDate('date', today()->toDateString())->get();
        $sheet = $rows->map(fn (TasksheetEntry $e) => sprintf('Team %s (id %d): plan: %s; result: %s.',
            $e->team?->name ?? '?', $e->team_id, self::excerpt($e->plan, 800) ?: 'empty', self::excerpt($e->result, 800) ?: 'empty'));

        $taskLines = $tasks->map(fn (Task $t) => self::taskLine($t))->all();
        $eventLines = $events->map(fn (Event $e) => self::eventLine($e))->all();
        $a = self::output('Open tasks:', $taskLines);
        $b = self::output('Events in the next 7 days:', $eventLines);

        return new ToolOutput(
            $a->text."\n\n".$b->text."\n\nToday's tasksheet:\n".($sheet->isEmpty() ? 'Nothing saved today.' : $sheet->implode("\n")),
            $a->refs + $b->refs,
        );
    }
}

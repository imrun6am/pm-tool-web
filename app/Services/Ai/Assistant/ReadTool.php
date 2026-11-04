<?php

namespace App\Services\Ai\Assistant;

use App\Models\Event;
use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use App\Services\Board\PlanToBoardService;
use Illuminate\Validation\ValidationException;

/** Looks something up, straight away, through the same scopes as the pages. */
abstract class ReadTool extends AssistantTool
{
    /** @throws ValidationException to tell the model what was wrong */
    abstract public function run(array $input, User $user): ToolOutput;

    /** Shown while it runs, e.g. "Checking your tasks". */
    abstract public function status(): string;

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['input' => $message]);
    }

    protected static function excerpt(?string $html, int $length = 300): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', PlanToBoardService::plainText((string) $html)), 0, $length, '…');
    }

    /** @return array{0: string, 1: array{ref: string, kind: string, title: string, url: string}} */
    protected static function taskLine(Task $task): array
    {
        $ref = "T{$task->id}";

        return [
            sprintf('[%s] %s; status %s; priority %s; assignee %s (id %s); due %s%s; release %s; team %s.',
                $ref, $task->title, $task->statusLabel(), $task->priority ?? 'none',
                $task->assignee?->name ?? 'none', $task->assignee_id ?? '-',
                $task->due_date?->toDateString() ?? 'none',
                $task->due_date && $task->due_date->isPast() && ! in_array($task->status, Task::DONE_STATUSES, true) ? ' (overdue)' : '',
                $task->release?->name ?? 'none', $task->team?->name ?? 'none'),
            ['ref' => $ref, 'kind' => 'Task', 'title' => $task->title, 'url' => route('tasks.show', $task)],
        ];
    }

    /** @return array{0: string, 1: array{ref: string, kind: string, title: string, url: string}} */
    protected static function eventLine(Event $event): array
    {
        $ref = "E{$event->id}";

        return [
            sprintf('[%s] %s (%s) %s%s%s; %s.', $ref, $event->title, $event->type,
                $event->starts_at->format($event->all_day ? 'Y-m-d' : 'Y-m-d H:i'),
                $event->ends_at && ! $event->all_day ? ' to '.$event->ends_at->format('H:i') : '',
                filled($event->location) ? " at {$event->location}" : '',
                $event->visibility),
            ['ref' => $ref, 'kind' => 'Event', 'title' => $event->title, 'url' => route('events.show', $event)],
        ];
    }

    /** @return array{ref: string, kind: string, title: string, url: string} */
    protected static function releaseRef(Release $release): array
    {
        return ['ref' => "R{$release->id}", 'kind' => 'Release', 'title' => $release->name, 'url' => route('releases.show', $release)];
    }

    /** @param  list<array{0: string, 1: array}>  $lines */
    protected static function output(string $heading, array $lines, string $empty = 'None found.'): ToolOutput
    {
        $refs = [];
        foreach ($lines as [, $ref]) {
            $refs[$ref['ref']] = $ref;
        }

        return new ToolOutput(
            $heading."\n".($lines === [] ? $empty : implode("\n", array_column($lines, 0))),
            $refs,
        );
    }
}

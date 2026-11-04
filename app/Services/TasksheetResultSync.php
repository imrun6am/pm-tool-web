<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Str;

/**
 * Keeps today's day-end result in step with the board: a finished task adds a
 * line to its assignee's row, and reopening it the same day takes the line back
 * out. No AI call, so a move to Done never waits on or fails with a model.
 * Lead feedback is never touched.
 */
class TasksheetResultSync
{
    public function __construct(private readonly TasksheetService $tasksheet) {}

    public function taskFinished(Task $task): void
    {
        $entry = $this->todaysEntry($task);
        if (! $entry || $entry->isFullDayLeave()) {
            return;
        }

        $line = $this->line($task);
        $result = (string) $entry->result;
        if (str_contains($result, $line)) {
            return;
        }

        // Join a list the result already ends with, so the lines read as one.
        $entry->result = str_ends_with($result, '</ul>')
            ? substr($result, 0, -strlen('</ul>')).$line.'</ul>'
            : $result.'<ul>'.$line.'</ul>';
        $entry->save();
    }

    public function taskReopened(Task $task): void
    {
        $entry = $this->todaysEntry($task);
        if (! $entry?->exists || ! str_contains((string) $entry->result, $this->line($task))) {
            return;
        }

        $entry->result = str_replace('<ul></ul>', '', Str::replaceFirst($this->line($task), '', $entry->result));
        $entry->save();
    }

    /** Today's row (possibly unsaved) on the team this task's work belongs to, if one can be told. */
    private function todaysEntry(Task $task): ?TasksheetEntry
    {
        $assignee = $task->assignee;
        if (! $assignee) {
            return null;
        }

        $teamIds = $assignee->currentTeamIds();
        $teamId = $task->team_id && $teamIds->contains($task->team_id)
            ? $task->team_id
            : ($teamIds->count() === 1 ? $teamIds->first() : null);

        return $teamId
            ? $this->tasksheet->resolveEntry(['team_id' => $teamId, 'user_id' => $assignee->id, 'date' => today()->toDateString()])
            : null;
    }

    /** The list item as the sanitizer stores it, so it can be found again later. */
    private function line(Task $task): string
    {
        $title = preg_replace('/\s+/u', ' ', trim($task->title));
        if ($task->parent) {
            $title = preg_replace('/\s+/u', ' ', trim($task->parent->title)).': '.$title;
        }

        return Str::between(HtmlSanitizer::clean('<ul><li>'.e('Completed: '.$title).'</li></ul>'), '<ul>', '</ul>');
    }
}

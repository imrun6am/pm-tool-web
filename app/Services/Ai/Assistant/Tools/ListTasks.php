<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\Task;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;
use App\Services\TaskService;

class ListTasks extends ReadTool
{
    private const LIMIT = 30;

    public function __construct(private readonly TaskService $tasks) {}

    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return 'List top-level tasks this person may open, filtered. Use for "what is overdue", "what is unassigned", "what is X working on", or to find a task by title.';
    }

    public function status(): string
    {
        return 'Looking through tasks';
    }

    public function properties(): array
    {
        return [
            'assignee' => ['type' => 'string', 'description' => '"me", "none" for unassigned, or a person\'s id.'],
            'team_id' => ['type' => 'integer'],
            'release_id' => ['type' => 'integer'],
            'status' => ['type' => 'string', 'enum' => array_keys(Task::STATUSES)],
            'overdue' => ['type' => 'boolean'],
            'open_only' => ['type' => 'boolean', 'description' => 'Leave out finished and archived tasks. Defaults to true.'],
            'title' => ['type' => 'string', 'description' => 'Words the title contains.'],
            'due_before' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'due_after' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        ];
    }

    public function run(array $input, User $user): ToolOutput
    {
        $assignee = $input['assignee'] ?? null;

        $query = $this->tasks->filtered(array_filter([
            'assignee_id' => $assignee === 'me' ? $user->id : ($assignee === 'none' ? TaskService::NONE : $assignee),
            'team_id' => $input['team_id'] ?? null,
            'release_id' => $input['release_id'] ?? null,
            'status' => $input['status'] ?? null,
            'overdue' => $input['overdue'] ?? null,
            'q' => $input['title'] ?? null,
            'due_before' => $input['due_before'] ?? null,
            'due_after' => $input['due_after'] ?? null,
        ]), $user);

        if (($input['open_only'] ?? true) && empty($input['status'])) {
            $query->whereNotIn('status', Task::DONE_STATUSES);
        }

        $total = (clone $query)->count();
        $lines = $query->limit(self::LIMIT)->get()->map(fn (Task $t) => self::taskLine($t))->all();

        return self::output($total > self::LIMIT ? "{$total} tasks; the first ".self::LIMIT.':' : "{$total} tasks:", $lines);
    }
}

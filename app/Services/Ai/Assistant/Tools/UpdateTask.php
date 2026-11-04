<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\TaskRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use App\Services\TaskService;
use App\Support\Duration;
use Illuminate\Support\Carbon;

class UpdateTask extends ActionTool
{
    /** What this tool can change; a person edits anything else on the task page. */
    private const FIELDS = ['title', 'status', 'priority', 'due_date', 'estimate'];

    public function __construct(private readonly TaskService $tasks) {}

    public function name(): string
    {
        return 'update_task';
    }

    public function description(): string
    {
        return "Draft a change to a task's title, status, priority, due date or estimate, for the person to confirm. Send only what changes; null clears a priority, due date or estimate.";
    }

    public function properties(): array
    {
        return [
            'task_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => array_keys(Task::STATUSES)],
            'priority' => ['anyOf' => [['type' => 'string', 'enum' => array_keys(Task::PRIORITIES)], ['type' => 'null']]],
            'due_date' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
            'estimate' => ['type' => ['string', 'null'], 'description' => 'Like "4h" or "1h 30m".'],
        ];
    }

    public function required(): array
    {
        return ['task_id'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $task = $this->visibleTask($input['task_id'] ?? null, $user);

        $changes = array_intersect_key($input, array_flip(self::FIELDS));
        if ($date = self::readable($changes['due_date'] ?? null)) {
            $changes['due_date'] = $date->toDateString();
        }
        // A re-check at confirm gets the estimate already turned into minutes.
        if (array_key_exists('estimate_minutes', $input)) {
            $changes['estimate_minutes'] = $input['estimate_minutes'];
        }

        // The form needs a title and the service a status; anything else not sent stays as it is.
        $checked = FormRules::check(TaskRequest::class, $changes + ['title' => $task->title, 'status' => $task->status], $user, ['task' => $task])->validated();

        $rows = $this->rows($task, $checked);
        if ($rows === []) {
            $this->fail('task_id', 'Nothing would change: the task already has those values.');
        }

        return new ActionDraft(['task_id' => $task->id] + $checked, [
            'kind' => 'change',
            'action' => 'Update task',
            'title' => $task->title,
            'eyebrow' => "Task #{$task->id}",
            'rows' => $rows,
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $task = $this->visibleTask($input['task_id'], $user);
        $this->tasks->update($task, collect($input)->except('task_id')->all());

        return new ActionResult('Task updated', route('tasks.show', $task), "Updated task [T{$task->id}].", [
            'ref' => "T{$task->id}", 'kind' => 'Task', 'title' => $task->title, 'url' => route('tasks.show', $task),
        ]);
    }

    /** @return list<array{label: string, from: ?string, to: ?string}> */
    private function rows(Task $task, array $checked): array
    {
        $rows = [];
        $add = function (string $label, mixed $from, mixed $to) use (&$rows) {
            if ($from !== $to) {
                $rows[] = ['label' => $label, 'from' => $from, 'to' => $to];
            }
        };

        if (array_key_exists('title', $checked)) {
            $add('Title', $task->title, $checked['title']);
        }
        if (array_key_exists('status', $checked)) {
            $add('Status', Task::STATUSES[$task->status] ?? $task->status, Task::STATUSES[$checked['status']] ?? $checked['status']);
        }
        if (array_key_exists('priority', $checked)) {
            $add('Priority', Task::PRIORITIES[$task->priority] ?? null, Task::PRIORITIES[$checked['priority']] ?? null);
        }
        if (array_key_exists('due_date', $checked)) {
            $add('Due', $task->due_date ? self::dayLabel($task->due_date) : null, filled($checked['due_date']) ? self::dayLabel(Carbon::parse($checked['due_date'])) : null);
        }
        if (array_key_exists('estimate_minutes', $checked)) {
            $format = fn ($m) => filled($m) ? Duration::format((int) $m) : null;
            $add('Estimate', $format($task->estimate_minutes), $format($checked['estimate_minutes']));
        }

        return $rows;
    }
}

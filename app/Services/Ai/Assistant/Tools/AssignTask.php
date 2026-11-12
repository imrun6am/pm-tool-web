<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\AssignTaskRequest;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use App\Services\BoardService;

class AssignTask extends ActionTool
{
    public function __construct(private readonly BoardService $board) {}

    public function name(): string
    {
        return 'assign_task';
    }

    public function description(): string
    {
        return 'Draft giving a task to someone, for the person to confirm. A task can have several assignees: send add true to put someone on it alongside the current ones, leave add out to make them its only assignee, or send assignee_id null to take everyone off. Use find_people for the id.';
    }

    public function properties(): array
    {
        return [
            'task_id' => ['type' => 'integer'],
            'assignee_id' => ['type' => ['integer', 'null']],
            'add' => ['type' => 'boolean', 'description' => 'true to add this person to the current assignees.'],
        ];
    }

    public function required(): array
    {
        return ['task_id', 'assignee_id'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $task = $this->visibleTask($input['task_id'] ?? null, $user);
        $current = $task->assigneeIds();

        // A confirmed draft comes back as its own output, with the whole list.
        if (isset($input['assignee_ids'])) {
            $ids = array_values(array_map('intval', (array) $input['assignee_ids']));
            FormRules::check(AssignTaskRequest::class, ['assignee_ids' => $ids], $user, ['task' => $task]);
            $add = count($ids) > count($current);
        } else {
            $checked = FormRules::check(AssignTaskRequest::class, ['assignee_id' => $input['assignee_id'] ?? null], $user, ['task' => $task])->validated();
            $assigneeId = $checked['assignee_id'] === null ? null : (int) $checked['assignee_id'];
            $add = (bool) ($input['add'] ?? false) && $assigneeId !== null;

            $ids = match (true) {
                $assigneeId === null => [],
                $add => array_values(array_unique([...$current, $assigneeId])),
                default => [$assigneeId],
            };
        }
        if ($ids === $current) {
            $this->fail('assignee_id', $add ? 'That person is already on the task.' : 'The task is already with that person.');
        }

        $names = fn (array $ids) => User::whereKey($ids)->orderBy('name')->pluck('name')->implode(', ') ?: null;

        return new ActionDraft(['task_id' => $task->id, 'assignee_ids' => $ids], [
            'kind' => 'change',
            'action' => $ids ? 'Assign task' : 'Unassign task',
            'title' => $task->title,
            'eyebrow' => "Task #{$task->id}",
            'rows' => [[
                'label' => count($ids) > 1 ? 'Assignees' : 'Assignee',
                'from' => $names($current),
                'to' => $names($ids),
            ]],
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $task = $this->visibleTask($input['task_id'], $user);
        // Drafts made before assignees became a list carry assignee_id.
        $ids = $input['assignee_ids'] ?? (isset($input['assignee_id']) ? [$input['assignee_id']] : []);
        $this->board->assign($task, ['assignee_ids' => $ids]);

        return new ActionResult($ids ? 'Task assigned' : 'Task unassigned', route('tasks.show', $task), "Done for task [T{$task->id}].", [
            'ref' => "T{$task->id}", 'kind' => 'Task', 'title' => $task->title, 'url' => route('tasks.show', $task),
        ]);
    }
}

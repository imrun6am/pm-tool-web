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
        return 'Draft giving a task to someone, or taking it off them with assignee_id null, for the person to confirm. Use find_people for the id.';
    }

    public function properties(): array
    {
        return [
            'task_id' => ['type' => 'integer'],
            'assignee_id' => ['type' => ['integer', 'null']],
        ];
    }

    public function required(): array
    {
        return ['task_id', 'assignee_id'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $task = $this->visibleTask($input['task_id'] ?? null, $user);

        $checked = FormRules::check(AssignTaskRequest::class, ['assignee_id' => $input['assignee_id'] ?? null], $user, ['task' => $task])->validated();
        $assigneeId = $checked['assignee_id'] === null ? null : (int) $checked['assignee_id'];

        if ($assigneeId === $task->assignee_id) {
            $this->fail('assignee_id', 'The task is already with that person.');
        }

        return new ActionDraft(['task_id' => $task->id, 'assignee_id' => $assigneeId], [
            'kind' => 'change',
            'action' => $assigneeId ? 'Assign task' : 'Unassign task',
            'title' => $task->title,
            'eyebrow' => "Task #{$task->id}",
            'rows' => [[
                'label' => 'Assignee',
                'from' => $task->assignee?->name,
                'to' => $assigneeId ? User::find($assigneeId)?->name : null,
            ]],
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $task = $this->visibleTask($input['task_id'], $user);
        $this->board->assign($task, $input['assignee_id']);

        return new ActionResult($input['assignee_id'] ? 'Task assigned' : 'Task unassigned', route('tasks.show', $task), "Done for task [T{$task->id}].", [
            'ref' => "T{$task->id}", 'kind' => 'Task', 'title' => $task->title, 'url' => route('tasks.show', $task),
        ]);
    }
}

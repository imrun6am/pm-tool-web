<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\CommentRequest;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use Illuminate\Auth\Access\AuthorizationException;

class AddComment extends ActionTool
{
    public function name(): string
    {
        return 'add_comment';
    }

    public function description(): string
    {
        return 'Draft a comment on a task, posted under the person\'s name once they confirm.';
    }

    public function properties(): array
    {
        return [
            'task_id' => ['type' => 'integer'],
            'body' => ['type' => 'string'],
        ];
    }

    public function required(): array
    {
        return ['task_id', 'body'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $task = $this->visibleTask($input['task_id'] ?? null, $user);
        $checked = FormRules::check(CommentRequest::class, ['body' => $input['body'] ?? null], $user)->validated();
        if (! $user->can('update', $task)) {
            throw new AuthorizationException('This person can’t comment on that task.');
        }

        return new ActionDraft(['task_id' => $task->id] + $checked, [
            'kind' => 'change',
            'action' => 'Post comment',
            'title' => $task->title,
            'eyebrow' => "Comment on task #{$task->id}",
            'body' => $checked['body'],
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $task = $this->visibleTask($input['task_id'], $user);
        $task->comments()->create(['user_id' => $user->id, 'body' => $input['body']]);

        return new ActionResult('Comment posted', route('tasks.show', $task), "Posted a comment on task [T{$task->id}].", [
            'ref' => "T{$task->id}", 'kind' => 'Task', 'title' => $task->title, 'url' => route('tasks.show', $task),
        ]);
    }
}

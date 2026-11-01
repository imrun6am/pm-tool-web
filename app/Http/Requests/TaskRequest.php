<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\KeepsTasksInViewerTeams;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    use KeepsTasksInViewerTeams;

    /**
     * Any signed-in user may collaborate on tasks, within the TaskPolicy:
     * developers and QA only on their own teams' tasks and releases.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if ($task = $this->route('task')) {
            return $user->can('update', $task); // an edit, or a subtask under it
        }

        return $user->can('create', [Task::class, $this->route('release')]);
    }

    protected function prepareForValidation(): void
    {
        // A subtask's team is its parent's, and a release's task is the
        // release's team's — only a standalone task or an edit takes one.
        if (! $this->routeIs('*subtasks.store') && ! $this->route('release')) {
            $this->pinViewerTeam(creating: ! $this->route('task'));
        }
    }

    public function rules(): array
    {
        return $this->withViewerTeamRules($this->baseRules(), creating: ! $this->route('task') && ! $this->route('release'));
    }

    /** @return array<string, array<int, mixed>> */
    private function baseRules(): array
    {
        return [
            // Optional link to a release; ignored where the route fixes it (a
            // release's task panel) or the parent does (a subtask).
            'release_id' => ['nullable', 'integer', Rule::exists('releases', 'id')],
            // Optional team; a linked task always takes its release's team.
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id'), $this->assigneeOnTeam(...)],
            'due_date' => ['nullable', 'date'],
            'phase' => ['nullable', Rule::in(array_keys(Release::PHASES))],
        ];
    }

    /**
     * Assignees are team-wise: they must belong to the release's owning team.
     * Keeping a task's unchanged assignee is allowed even if that person has
     * since left the team. A task with no release may go to anyone.
     */
    private function assigneeOnTeam(string $attribute, mixed $value, \Closure $fail): void
    {
        /** @var Task|null $task */
        $task = $this->route('task');
        $release = $this->route('release') ?? $this->targetRelease($task);

        if (! $release || $value === null) {
            return;
        }

        if ($task && (int) $value === $task->assignee_id) {
            return; // unchanged
        }

        $isMember = $release->team->members()->whereKey((int) $value)->exists();
        if (! $isMember) {
            $fail('The assignee must be a member of the release’s team.');
        }
    }

    /**
     * The release the task will belong to. A subtask (new, where the routed
     * task is the parent, or existing) carries its parent's; otherwise it is
     * the one the request links (none for `release_id: null`), else the task's
     * current one.
     */
    private function targetRelease(?Task $task): ?Release
    {
        if ($task && ($this->routeIs('*subtasks.store') || $task->isSubtask())) {
            return $task->release;
        }

        if ($task && ! $this->has('release_id')) {
            return $task->release;
        }

        return $this->filled('release_id') ? Release::find($this->integer('release_id')) : null;
    }
}

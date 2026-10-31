<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may collaborate on tasks.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
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
     * since left the team.
     */
    private function assigneeOnTeam(string $attribute, mixed $value, \Closure $fail): void
    {
        /** @var Task|null $task */
        $task = $this->route('task');
        $release = $this->route('release') ?? $task?->release;

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
}

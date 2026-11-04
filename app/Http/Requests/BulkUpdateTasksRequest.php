<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Changing several tasks at once, shared by web and API. All of them or none:
 * every id is validated and authorized, and a new assignee must suit every task.
 *
 * A field left out or blank changes nothing, and TaskService::NONE clears it.
 */
class BulkUpdateTasksRequest extends FormRequest
{
    public const MAX_TASKS = 200;

    /** @var Collection<int, Task>|null */
    private ?Collection $tasks = null;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_TASKS],
            'ids.*' => ['required', 'integer', 'distinct:strict', Rule::exists('tasks', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['nullable', Rule::in([...array_keys(Task::PRIORITIES), TaskService::NONE])],
            'assignee_id' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== TaskService::NONE && ! User::whereKey((int) $value)->exists()) {
                    $fail('That person doesn’t exist.');
                }
            }],
            'add_label_id' => ['nullable', 'integer', Rule::exists('labels', 'id')],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $gate = Gate::forUser($this->user());
            $this->tasks()->each(fn (Task $task) => $gate->authorize('update', $task));

            if ($this->changes() === []) {
                $validator->errors()->add('ids', 'Choose something to change.');
            }

            $assigneeId = $this->changes()['attributes']['assignee_id'] ?? null;
            if ($assigneeId !== null) {
                $this->checkAssignee($validator, $assigneeId);
            }
        }];
    }

    /**
     * What to change: `attributes` for the task row and `add_label_id`, holding
     * only what was asked for.
     *
     * @return array{attributes?: array<string, mixed>, add_label_id?: int}
     */
    public function changes(): array
    {
        $attributes = [];
        foreach (['status', 'priority', 'assignee_id'] as $field) {
            $value = $this->input($field);
            if (filled($value)) {
                $attributes[$field] = $value === TaskService::NONE ? null : $value;
            }
        }
        if (isset($attributes['assignee_id'])) {
            $attributes['assignee_id'] = (int) $attributes['assignee_id'];
        }

        return array_filter([
            'attributes' => $attributes,
            'add_label_id' => $this->filled('add_label_id') ? $this->integer('add_label_id') : null,
        ]);
    }

    /** @return Collection<int, Task> */
    public function tasks(): Collection
    {
        return $this->tasks ??= Task::with('release.team')->whereKey($this->input('ids'))->get();
    }

    /** The same rules as assigning one task: active, on a linked release's team, and a teammate for a developer or QA. */
    private function checkAssignee(Validator $validator, int $assigneeId): void
    {
        if (! User::active()->whereKey($assigneeId)->exists()) {
            $validator->errors()->add('assignee_id', 'This person’s account is deactivated.');

            return;
        }

        if ($this->user()->hasLimitedAccess() && ! User::teammatesOf($this->user())->whereKey($assigneeId)->exists()) {
            $validator->errors()->add('assignee_id', 'Choose someone on your team.');

            return;
        }

        foreach ($this->tasks() as $task) {
            if ($task->release && ! $task->release->team->members()->whereKey($assigneeId)->exists()) {
                $validator->errors()->add('assignee_id', $task->reference().' is in a release, so its assignee must be on that release’s team.');

                return;
            }
        }
    }
}

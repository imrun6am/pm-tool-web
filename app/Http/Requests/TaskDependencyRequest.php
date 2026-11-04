<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Services\TaskDependencyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Making a task wait for another, shared by web and API. The other task comes
 * as `reference` ("PM-123" or "123"). One the caller can't see reads the same
 * as one that doesn't exist.
 */
class TaskDependencyRequest extends FormRequest
{
    private ?Task $blocker = null;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['reference' => ['required', 'string', 'max:40']];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $task = $this->route('task');
            $id = Task::idFromReference($this->string('reference')->toString());
            $blocker = $id ? Task::find($id) : null;

            if (! $blocker || ! $blocker->isVisibleTo($this->user())) {
                $validator->errors()->add('reference', 'No task found with that key. Use one like '.Task::KEY_PREFIX.'-123.');
            } elseif ($blocker->is($task)) {
                $validator->errors()->add('reference', 'A task can’t wait for itself.');
            } elseif (app(TaskDependencyService::class)->wouldCycle($task, $blocker)) {
                $validator->errors()->add('reference', $blocker->reference().' is already waiting for this task, directly or through another.');
            } else {
                $this->blocker = $blocker;
            }
        }];
    }

    public function blocker(): Task
    {
        return $this->blocker;
    }
}

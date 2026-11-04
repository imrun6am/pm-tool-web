<?php

namespace App\Http\Requests;

use App\Models\RecurringTask;
use App\Models\Task;
use App\Support\Duration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Adding a recurring rule, shared by web and API. Routes keep this to leads. */
class RecurringTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isLead() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('estimate')) {
            $text = $this->input('estimate');
            $this->merge(['estimate_minutes' => filled($text) ? (Duration::parse($text) ?? 'unreadable') : null]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')->whereNull('deleted_at')],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'estimate_minutes' => ['nullable', 'integer', 'min:1', 'max:125000'],
            'frequency' => ['required', Rule::in(array_keys(RecurringTask::FREQUENCIES))],
            'next_run_on' => ['required', 'date', 'after_or_equal:today'],
            'due_in_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estimate_minutes.integer' => 'Enter the estimate like 4h or 1h 30m.',
            'estimate_minutes.min' => 'Enter the estimate like 4h or 1h 30m.',
            'next_run_on.after_or_equal' => 'The first run can’t be in the past.',
            'assignee_id.exists' => 'Choose an active person.',
        ];
    }

    /** @return array<string, mixed> */
    public function ruleAttributes(): array
    {
        return array_merge($this->validated(), ['due_in_days' => (int) $this->validated('due_in_days')]);
    }
}

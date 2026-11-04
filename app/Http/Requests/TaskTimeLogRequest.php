<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Policies\TaskTimeLogPolicy;
use App\Support\Duration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Adding or changing a time entry, shared by web and API. Time arrives as
 * `minutes`, or as `duration` text such as "1h 30m", which the task page sends.
 */
class TaskTimeLogRequest extends FormRequest
{
    private const MINUTES_IN_DAY = 1440;

    public function authorize(): bool
    {
        $log = $this->route('timeLog');

        return $log
            ? $this->user()->can('update', $log)
            : $this->user()->can('create', [TaskTimeLog::class, $this->route('task')]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('duration')) {
            $this->merge(['minutes' => Duration::parse($this->string('duration')->toString())]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'minutes' => ['required', 'integer', 'min:1', 'max:'.self::MINUTES_IN_DAY],
            'logged_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'minutes.required' => 'Enter the time spent, like 1h 30m.',
            'minutes.integer' => 'Enter the time spent, like 1h 30m.',
            'minutes.min' => 'Enter the time spent, like 1h 30m.',
            'minutes.max' => 'One entry can’t be more than 24 hours.',
            'logged_on.before_or_equal' => 'Time can’t be logged for a future date.',
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkDateIsRecentEnough($validator);
            $this->checkDayTotal($validator);
        }];
    }

    /** Backdating further than the edit window takes someone who could correct it anyway. */
    private function checkDateIsRecentEnough(Validator $validator): void
    {
        $log = $this->route('timeLog');
        $task = $log ? $log->task : $this->route('task');

        if ($this->user()->can('correct', [TaskTimeLog::class, $task])) {
            return;
        }

        if (Carbon::parse($this->input('logged_on'))->lt(today()->subDays(TaskTimeLogPolicy::EDIT_WINDOW_DAYS))) {
            $validator->errors()->add('logged_on', 'Time can only be logged for the last '.TaskTimeLogPolicy::EDIT_WINDOW_DAYS.' days. Ask a lead to add older time.');
        }
    }

    private function checkDayTotal(Validator $validator): void
    {
        $log = $this->route('timeLog');

        $logged = TaskTimeLog::query()
            ->where('user_id', $log ? $log->user_id : $this->user()->id)
            ->whereDate('logged_on', Carbon::parse($this->input('logged_on'))->toDateString())
            ->when($log, fn ($q) => $q->whereKeyNot($log->id))
            ->sum('minutes');

        if ($logged + (int) $this->input('minutes') > self::MINUTES_IN_DAY) {
            $validator->errors()->add('minutes', 'That would take the day’s total past 24 hours.');
        }
    }
}

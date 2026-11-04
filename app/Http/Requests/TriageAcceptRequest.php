<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Accepting a request: what the new task needs, using the same assignee rules as any task. */
class TriageAcceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('triageItem'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'release_id' => ['nullable', 'integer', Rule::exists('releases', 'id')],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id'), function (string $attribute, mixed $value, \Closure $fail) {
                if ($value === null) {
                    return;
                }
                if (! User::active()->whereKey((int) $value)->exists()) {
                    $fail('This person’s account is deactivated.');

                    return;
                }
                $release = $this->filled('release_id') ? Release::find($this->integer('release_id')) : null;
                if ($release && ! $release->team->members()->whereKey((int) $value)->exists()) {
                    $fail('The assignee must be a member of the release’s team.');
                }
            }],
        ];
    }
}

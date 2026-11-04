<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sending a request, shared by web and API. Developers and QA send to their own team. */
class TriageItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user?->hasLimitedAccess() && ! $this->filled('team_id')) {
            $teams = $user->currentTeamIds();
            $this->merge(['team_id' => $teams->count() === 1 ? $teams->first() : null]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id'), function (string $attribute, mixed $value, \Closure $fail) {
                $user = $this->user();
                if ($value !== null && $user->hasLimitedAccess() && ! $user->currentTeamIds()->contains((int) $value)) {
                    $fail('Choose one of your teams.');
                }
            }],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'label_id' => ['nullable', 'integer', Rule::exists('labels', 'id')],
        ];
    }
}

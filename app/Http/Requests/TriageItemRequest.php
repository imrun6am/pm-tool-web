<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\User;
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
        if ($this->user()?->hasLimitedAccess() && ! $this->filled('team_id')) {
            $this->merge(['team_id' => self::defaultTeamId($this->user())]);
        }
    }

    /** Where a request with no team goes: the one team of someone limited to one, otherwise nowhere yet. */
    public static function defaultTeamId(User $user): ?int
    {
        if (! $user->hasLimitedAccess()) {
            return null;
        }

        $teams = $user->currentTeamIds();

        return $teams->count() === 1 ? $teams->first() : null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return self::fields($this->user());
    }

    /**
     * A request's fields, shared with the import review form so the two can't drift.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function fields(User $user): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id'), function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                if ($value !== null && $user->hasLimitedAccess() && ! $user->currentTeamIds()->contains((int) $value)) {
                    $fail('Choose one of your teams.');
                }
            }],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'label_id' => ['nullable', 'integer', Rule::exists('labels', 'id')],
        ];
    }
}

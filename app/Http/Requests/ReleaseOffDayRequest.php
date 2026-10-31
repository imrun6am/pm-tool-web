<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReleaseOffDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageReleases() ?? false;
    }

    public function rules(): array
    {
        $release = $this->route('release');

        return [
            'date' => [
                'required', 'date',
                'after_or_equal:'.$release->start_date->toDateString(),
                'before_or_equal:'.$release->end_date->toDateString(),
                Rule::unique('release_off_days', 'date')->where('release_id', $release->id),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'The off-day must be within the release window.',
            'date.before_or_equal' => 'The off-day must be within the release window.',
            'date.unique' => 'That date is already marked as an off-day for this release.',
        ];
    }
}

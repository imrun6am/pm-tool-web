<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageWorkspace() ?? false;
    }

    public function rules(): array
    {
        $teamId = $this->route('team')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('teams', 'name')
                    ->whereNull('archived_at')
                    ->ignore($teamId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Any active user may lead — role is irrelevant.
            'team_lead_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'Color must be a hex value like #0891b2.',
            'name.unique' => 'An active team with this name already exists.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageWorkspace() ?? false;
    }

    public function rules(): array
    {
        $projectId = $this->route('project')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                // Unique among active (non-archived) projects, ignoring this one.
                Rule::unique('projects', 'name')
                    ->whereNull('archived_at')
                    ->ignore($projectId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'Color must be a hex value like #4f46e5.',
            'name.unique' => 'An active project with this name already exists.',
        ];
    }
}

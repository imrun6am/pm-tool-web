<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\Task;
use App\Support\Duration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A task in a release template, shared by web and API. Routes keep this to leads. */
class ReleaseTemplateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageReleases() ?? false;
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
            'phase' => ['nullable', Rule::in(array_keys(Release::PHASES))],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'estimate_minutes' => ['nullable', 'integer', 'min:1', 'max:125000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estimate_minutes.integer' => 'Enter the estimate like 4h or 1h 30m.',
            'estimate_minutes.min' => 'Enter the estimate like 4h or 1h 30m.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\PerformanceCompetency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a competency-catalog create/edit. Authorization is the
 * `manage-competencies` gate (org-level leads only).
 */
class PerformanceCompetencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-competencies') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(array_keys(PerformanceCompetency::CATEGORIES))],
            'role_scope' => ['required', Rule::in(array_keys(PerformanceCompetency::ROLE_SCOPES))],
            'cadence' => ['required', Rule::in(array_keys(PerformanceCompetency::CADENCES))],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'active' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Unchecked checkbox → explicit false.
        $this->merge(['active' => $this->boolean('active')]);
    }
}

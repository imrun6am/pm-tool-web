<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The review form: one row per proposal. Only ticked rows are validated (an
 * unticked row may stay half-edited), each by the request form's own rules.
 */
class ConfirmTriageImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // the controller checks the import policy
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('requests');
        if (! is_array($rows)) {
            return;
        }

        $this->merge(['requests' => array_map(function ($row) {
            if (! is_array($row)) {
                return $row;
            }

            // JSON clients send a real boolean, which exclude_unless below wouldn't match.
            $row['include'] = filter_var($row['include'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';

            return blank($row['team_id'] ?? null)
                ? ['team_id' => TriageItemRequest::defaultTeamId($this->user())] + $row
                : $row;
        }, $rows)]);
    }

    public function rules(): array
    {
        $rules = [
            'requests' => ['required', 'array'],
            'requests.*.include' => ['nullable', 'boolean'],
        ];

        foreach (TriageItemRequest::fields($this->user()) as $field => $fieldRules) {
            $rules["requests.*.{$field}"] = ['exclude_unless:requests.*.include,1', ...$fieldRules];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'requests.*.title' => 'title',
            'requests.*.description' => 'details',
            'requests.*.team_id' => 'team',
            'requests.*.priority' => 'priority',
            'requests.*.label_id' => 'type',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The tasksheet export form — the same inputs the inline report accepted.
 * Who may export which team or member is TasksheetExportPolicy@create's call;
 * a reversed range is swapped by TasksheetService::reportRange(), not refused.
 */
class TasksheetExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'team' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'member' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            // Unknown values are dropped by TasksheetService::parseFilters().
            'attendance' => ['nullable', 'string', 'max:20'],
            'fill' => ['nullable', 'string', 'max:20'],
            'leave' => ['nullable', 'string', 'max:20'],
        ];
    }
}

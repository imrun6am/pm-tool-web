<?php

namespace App\Http\Requests;

use App\Models\KpiReport;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KpiReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            // A year at most: every member's whole history goes into one request.
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today', function ($attribute, $value, $fail) {
                if (strtotime($value) - strtotime($this->input('from')) > 366 * 86400) {
                    $fail('The review period can be at most one year.');
                }
            }],
            'format' => ['required', Rule::in(array_keys(KpiReport::FORMATS))],
            'scope' => ['required', Rule::in(array_keys(KpiReport::SCOPES))],
            'team_id' => ['nullable', 'required_if:scope,'.KpiReport::SCOPE_TEAM, 'integer', Rule::exists('teams', 'id')],
            'member_id' => ['nullable', 'required_if:scope,'.KpiReport::SCOPE_MEMBER, 'integer',
                Rule::exists('users', 'id')->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])],
        ];
    }

    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The end date must be on or after the start date.',
            'to.before_or_equal' => 'The end date cannot be in the future.',
            'team_id.required_if' => 'Pick the team to report on.',
            'member_id.required_if' => 'Pick the member to report on.',
            'member_id.exists' => 'Only developers and QA are scored.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\QuickLink;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            // http/https only — blocks javascript: and friends.
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'visibility' => ['nullable', Rule::in(array_keys(QuickLink::VISIBILITIES))],
            'release_id' => ['nullable', 'integer', 'exists:releases,id'],
        ];
    }

    /** Limited roles (developer/QA) are private-only — shared is rejected, not downgraded. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->user()->hasLimitedAccess()
                && $this->input('visibility') === QuickLink::VISIBILITY_SHARED) {
                $validator->errors()->add('visibility', 'Your role can only create private links.');
            }
        });
    }
}

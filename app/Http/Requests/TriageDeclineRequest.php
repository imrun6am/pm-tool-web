<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Declining a request. The reporter deserves to hear why. */
class TriageDeclineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('triageItem'));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => 'Say why, so the person who sent it knows.'];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Adding or changing a label, shared by web and API. Routes keep this to leads. */
class LabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageWorkspace() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40', Rule::unique('labels', 'name')->ignore($this->route('label'))],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}

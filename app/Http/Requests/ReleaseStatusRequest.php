<?php

namespace App\Http\Requests;

use App\Models\ReleaseStatusUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Posting a status update, shared by web and API. */
class ReleaseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageReleases() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(ReleaseStatusUpdate::STATUSES))],
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['note.required' => 'Say where it stands.'];
    }
}

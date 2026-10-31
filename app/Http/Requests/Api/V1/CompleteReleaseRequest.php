<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Marking a release shipped. Authorization is the `manage-releases`
 * middleware on the route; this only shapes the optional notes.
 */
class CompleteReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageReleases() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'completion_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

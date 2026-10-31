<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Self-service profile edit. Deliberately does not accept `role` or
 * `deactivated_at` — a user may not promote or reinstate themselves; that is
 * user management, and it lives behind the manage-users middleware.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}

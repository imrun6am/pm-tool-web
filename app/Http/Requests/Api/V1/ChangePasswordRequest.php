<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Self-service password change. `current_password` is verified against the
 * signed-in user, so possession of a token alone is not enough to change the
 * credential that token was issued from.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Guard must be named: `current_password` defaults to the web guard,
            // which holds no user on a stateless API request.
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'The current password is incorrect.',
        ];
    }
}

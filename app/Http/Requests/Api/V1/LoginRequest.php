<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Credential exchange for a desktop client.
 *
 * Throttling is *not* done here — the route carries `throttle:login`, which
 * meters attempts per email + IP and answers a genuine 429 with a Retry-After
 * header. Duplicating a counter in this class would mean two limits with two
 * different keys and two different status codes for the same condition.
 *
 * Failures are deliberately indistinguishable: an unknown email and a wrong
 * password produce the same message, so the endpoint cannot be used to
 * enumerate which addresses hold accounts.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // Names the token so a user can recognise and revoke a device later.
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Resolve the credentials to a user, or fail with a credentials error.
     *
     * A deactivated or deleted account is rejected here rather than after a
     * token is minted — the token must never exist in the first place.
     *
     * @throws ValidationException
     */
    public function authenticateUser(): User
    {
        $password = $this->string('password')->toString();
        $provider = Auth::createUserProvider('users');

        /** @var User|null $user */
        $user = $provider->retrieveByCredentials([
            'email' => $this->string('email')->toString(),
            'password' => $password,
        ]);

        if (! $user || ! $provider->validateCredentials($user, ['password' => $password])) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive() || $user->trashed()) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        return $user;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\V1\AuthUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own account: identity, effective permissions, and
 * self-service edits.
 */
class ProfileController extends ApiController
{
    /**
     * The caller, their permission flags, their teams, and the teams they lead.
     *
     * A desktop client calls this on start-up and builds its navigation from
     * the flags rather than from its own copy of the role rules.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['teams', 'ledTeams']);

        return $this->ok(new AuthUserResource($user));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        // Changing the address invalidates the previous verification.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $this->ok(
            new AuthUserResource($user->load(['teams', 'ledTeams'])),
            'Profile updated.'
        );
    }

    /**
     * Change the caller's password.
     *
     * Every *other* device is signed out: a password change is what a user
     * does when they suspect their credential is compromised, so leaving the
     * other sessions alive would defeat the point. The token making this
     * request survives, so the client that performed the change stays in.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $user->update(['password' => $request->string('password')->toString()]);

        $user->tokens()->when($currentTokenId, fn ($q) => $q->whereKeyNot($currentTokenId))->delete();

        return $this->message('Password changed. Other devices have been signed out.');
    }
}

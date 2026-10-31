<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\V1\AuthUserResource;
use App\Http\Resources\V1\PersonalAccessTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token lifecycle for desktop clients.
 *
 * There is no registration endpoint here, by design: self-registration is
 * disabled across the product, and accounts are created only by a user who may
 * manage users (see Api\V1\UserController).
 */
class AuthController extends ApiController
{
    /**
     * Exchange credentials for a bearer token.
     *
     * The plain-text token is returned exactly once — Sanctum stores only a
     * hash — so a client that loses it must log in again rather than ask for
     * it back.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticateUser();

        $token = $user->createToken($request->string('device_name')->toString());

        $user->load(['teams', 'ledTeams']);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'user' => new AuthUserResource($user),
            ],
            'message' => 'Signed in.',
        ], 201);
    }

    /** Revoke the token this request was made with — sign out this device only. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->message('Signed out on this device.');
    }

    /** Revoke every token for the user — sign out everywhere. */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->message('Signed out on all devices.');
    }

    /** The caller's own signed-in devices, most recently used first. */
    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->orderByRaw('last_used_at is null')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();

        return $this->ok(PersonalAccessTokenResource::collection($tokens));
    }

    /**
     * Revoke one device.
     *
     * The token is resolved through the user's own relation, so a request for
     * somebody else's token id finds nothing and 404s rather than revoking it.
     */
    public function revokeToken(Request $request, string $tokenId): JsonResponse
    {
        /** @var PersonalAccessToken|null $token */
        $token = $request->user()->tokens()->whereKey($tokenId)->first();

        abort_if($token === null, 404, 'Device not found.');

        $token->delete();

        return $this->message('Device signed out.');
    }
}

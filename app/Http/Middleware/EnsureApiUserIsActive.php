<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API counterpart of EnsureUserIsActive. An API request carries no session
 * to invalidate, so a deactivated (or deleted) account's presented token is
 * revoked outright and the request is refused as unauthenticated — the client
 * is signed out rather than left retrying a token that will never work again.
 */
class EnsureApiUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->isActive() || $user->trashed())) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            abort(401, 'Your account has been deactivated.');
        }

        return $next($request);
    }
}

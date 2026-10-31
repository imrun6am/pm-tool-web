<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageReleases
{
    /**
     * Allow release planning and team-membership management for admins and
     * team leads; everyone else is forbidden.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canManageReleases()) {
            abort(403, 'This action requires an administrator or team lead account.');
        }

        return $next($request);
    }
}

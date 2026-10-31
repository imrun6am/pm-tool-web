<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageUsers
{
    /**
     * Only Admins and CTOs may reach user management.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canManageUsers()) {
            abort(403, 'Managing users requires an administrator or CTO account.');
        }

        return $next($request);
    }
}

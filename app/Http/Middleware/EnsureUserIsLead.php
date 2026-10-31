<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsLead
{
    /**
     * Allow the request only for the leadership tier — admin, CTO, tech lead,
     * and team lead, who all share the same access. Everyone else is forbidden.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isLead()) {
            abort(403, 'This action requires a leadership account.');
        }

        return $next($request);
    }
}

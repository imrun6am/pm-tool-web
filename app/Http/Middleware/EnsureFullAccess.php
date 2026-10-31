<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullAccess
{
    /**
     * Planning surfaces (releases, projects, teams, activity) are hidden from
     * limited roles (developer, QA) — they work from the board, calendar,
     * notes, meetings, and tasksheet only.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasLimitedAccess()) {
            abort(403, 'This section is not available for your role.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Release;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReleaseIsVisible
{
    /**
     * Guards every `releases/{release}/…` route open to all signed-in users:
     * developers and QA may only reach their own teams' releases (and their
     * documents, comments, tasks, phases and off-days).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $release = $request->route('release');

        if ($release instanceof Release && ! $request->user()?->can('view', $release)) {
            abort(403, 'This release belongs to another team.');
        }

        return $next($request);
    }
}

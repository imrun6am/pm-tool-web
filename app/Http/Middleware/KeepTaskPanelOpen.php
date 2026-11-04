<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Actions end with back(), which from the task side panel means the page behind
 * it. The panel marks its forms with `_panel`, so their answer comes back to the
 * panel and it stays open. tasks.panel does its own authorization.
 */
class KeepTaskPanelOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $taskId = $request->input('_panel');
        if ($response instanceof RedirectResponse && ! $request->isMethodSafe() && ctype_digit((string) $taskId)) {
            return redirect()->route('tasks.panel', (int) $taskId);
        }

        return $response;
    }
}

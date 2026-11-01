<?php

namespace App\Http\Middleware;

use App\Support\PendingCelebration;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands a celebration raised during the request to the client: flashed to the
 * session for a redirect (layouts/toasts shows it on the next page), or as an
 * `X-Celebrate` header (URL-encoded JSON) on any other response — the board's
 * JSON moves and the API.
 */
class ShareCelebration
{
    public const HEADER = 'X-Celebrate';

    public function __construct(private readonly PendingCelebration $pending) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $celebration = $this->pending->pull();
        if ($celebration === null || $response->getStatusCode() >= 400) {
            return $response;
        }

        if ($response instanceof RedirectResponse && $request->hasSession()) {
            $request->session()->flash('celebrate', $celebration['message']);
        } else {
            $response->headers->set(self::HEADER, rawurlencode(json_encode($celebration)));
        }

        return $response;
    }
}

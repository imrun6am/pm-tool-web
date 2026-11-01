<?php

namespace App\Http\Controllers\Ai\Concerns;

use App\Services\Ai\AiException;
use Closure;
use Illuminate\Http\JsonResponse;

/**
 * Runs a Claude-backed action and answers JSON either way: the result on
 * success, or `{message}` with 503 (retry may help) / 409 (it won't — the
 * feature is off, unconfigured, or Claude declined) when it fails.
 */
trait RespondsWithAi
{
    protected function aiJson(Closure $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], $e->retryable ? 503 : 409);
        }
    }
}

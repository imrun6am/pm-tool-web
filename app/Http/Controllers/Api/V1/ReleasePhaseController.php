<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\ReleasePhaseResource;
use App\Models\Release;
use Illuminate\Http\JsonResponse;

/**
 * A release's phases. Always exactly four, always in order — so this
 * collection is returned whole rather than paginated.
 *
 * Phases have no create/update/delete of their own: they are rewritten as a
 * set whenever the release is saved, which is what keeps them consistent with
 * the release window.
 */
class ReleasePhaseController extends ApiController
{
    public function index(Release $release): JsonResponse
    {
        return $this->ok(ReleasePhaseResource::collection($release->phases()->get()));
    }
}

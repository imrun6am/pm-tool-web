<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ReleaseStatusRequest;
use App\Http\Resources\V1\ReleaseStatusUpdateResource;
use App\Models\Release;
use App\Services\ReleaseStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Written updates on a release. Anyone who can see it reads them; leads post. */
class ReleaseStatusController extends ApiController
{
    public function __construct(private readonly ReleaseStatusService $statuses) {}

    public function index(Release $release): AnonymousResourceCollection
    {
        return ReleaseStatusUpdateResource::collection($this->statuses->history($release));
    }

    public function store(ReleaseStatusRequest $request, Release $release): JsonResponse
    {
        $update = $this->statuses->post($release, $request->user(), $request->validated('status'), $request->validated('note'));

        return $this->created(new ReleaseStatusUpdateResource($update->load('author')), 'Status update posted.');
    }
}

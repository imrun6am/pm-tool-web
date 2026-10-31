<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ReleaseOffDayRequest;
use App\Http\Resources\V1\ReleaseOffDayResource;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use App\Services\ReleaseOffDayService;
use Illuminate\Http\JsonResponse;

class ReleaseOffDayController extends ApiController
{
    public function __construct(private readonly ReleaseOffDayService $offDays) {}

    public function index(Release $release): JsonResponse
    {
        return $this->ok(ReleaseOffDayResource::collection($this->offDays->forRelease($release)));
    }

    public function store(ReleaseOffDayRequest $request, Release $release): JsonResponse
    {
        $offDay = $this->offDays->add($release, $request->validated());

        return $this->created(new ReleaseOffDayResource($offDay), 'Off-day marked.');
    }

    public function markWeekends(Release $release): JsonResponse
    {
        $added = $this->offDays->markWeekends($release);

        return $this->ok(
            ReleaseOffDayResource::collection($this->offDays->forRelease($release)),
            $added ? "Marked {$added} weekend day(s) as off." : 'All weekends were already marked.'
        );
    }

    public function destroy(Release $release, ReleaseOffDay $offDay): JsonResponse
    {
        $this->offDays->remove($release, $offDay);

        return $this->message('Off-day removed.');
    }
}

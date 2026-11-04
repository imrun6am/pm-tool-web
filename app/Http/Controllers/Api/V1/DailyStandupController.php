<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\GenerateStandupRequest;
use App\Http\Resources\V1\MeetingNoteResource;
use App\Models\DailyStandup;
use App\Services\DailyStandupService;
use App\Support\StandupRun;
use Illuminate\Http\JsonResponse;

class DailyStandupController extends ApiController
{
    public function __construct(private readonly DailyStandupService $standups) {}

    /** Generate or refresh on request, which also brings back a deleted standup. */
    public function store(GenerateStandupRequest $request): JsonResponse
    {
        $team = $request->team();
        $this->authorize('generate', [DailyStandup::class, $team]);

        $run = $this->standups->generate($team, $request->day(), restore: true);

        if (! $run->note) {
            return response()->json(['message' => $run->message()], 422);
        }

        $resource = new MeetingNoteResource($run->note->load(['author', 'release', 'event', 'attendees', 'standup.team']));

        return $run->status === StandupRun::CREATED
            ? $this->created($resource, $run->message())
            : $this->ok($resource, $run->message());
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TeamSummaryResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Services\CapacityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Each team member's available time per week against the work due that week. */
class CapacityController extends ApiController
{
    public function __construct(private readonly CapacityService $capacity) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['team_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date']]);

        $teams = $this->capacity->teamsFor($request->user());
        $team = $request->filled('team_id') ? $teams->firstWhere('id', $request->integer('team_id')) : $teams->first();
        abort_if($request->filled('team_id') && ! $team, 403);

        $result = $team ? $this->capacity->forTeam($team, $request->filled('from') ? Carbon::parse($request->date('from')) : null) : null;

        return $this->ok([
            'teams' => TeamSummaryResource::collection($teams)->resolve($request),
            'team' => $team ? (new TeamSummaryResource($team))->resolve($request) : null,
            'weeks' => collect($result['weeks'] ?? [])->map(fn (array $w) => [
                'start' => $w['start']->toDateString(),
                'end' => $w['end']->toDateString(),
                'current' => $w['current'],
            ])->all(),
            'people' => collect($result['people'] ?? [])->map(fn (array $row) => [
                'user' => (new UserSummaryResource($row['user']))->resolve($request),
                'weeks' => $row['cells'],
                'later' => $row['later'],
            ])->all(),
        ]);
    }
}

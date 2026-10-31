<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TeamRequest;
use App\Http\Resources\V1\ReleaseSummaryResource;
use App\Http\Resources\V1\TeamResource;
use App\Models\Team;
use App\Services\OverlapChecker;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends ApiController
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->teams->filtered([
            'status' => $request->input('status'),
            'search' => $request->filled('search') ? $request->string('search')->toString() : null,
        ]);

        return $this->paginate($request, $query, TeamResource::class);
    }

    /**
     * A team with its members, its lead, and its schedule — including which of
     * its releases collide, since a double-booked team is the point of the
     * conflict rule and a client should not have to recompute it.
     */
    public function show(Request $request, Team $team, OverlapChecker $overlap): JsonResponse
    {
        $team->load(['members', 'teamLead', 'releases.project', 'releases.team'])
            ->loadCount(['releases', 'members']);

        $releases = $team->releases->sortBy('start_date')->values();
        $conflicts = $overlap->flagConflicts($releases);

        return $this->ok(
            (new TeamResource($team))->additional([
                'releases' => ReleaseSummaryResource::collection($releases)->resolve($request),
                'conflicting_release_ids' => array_map('intval', array_keys($conflicts)),
            ])
        );
    }

    public function store(TeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        return $this->created(
            new TeamResource($team->load('teamLead')->loadCount(['releases', 'members'])),
            "Team “{$team->name}” created."
        );
    }

    public function update(TeamRequest $request, Team $team): JsonResponse
    {
        $team->update($request->validated());

        return $this->ok(
            new TeamResource($team->load('teamLead')->loadCount(['releases', 'members'])),
            "Team “{$team->name}” updated."
        );
    }

    /** A team that owns releases is archived, never hard-deleted. */
    public function destroy(Team $team): JsonResponse
    {
        abort_if(
            ! $this->teams->isDeletable($team),
            422,
            'This team owns releases and cannot be deleted. Archive it instead.'
        );

        $name = $team->name;
        $team->delete();

        return $this->message("Team “{$name}” deleted.");
    }

    public function archive(Team $team): JsonResponse
    {
        $this->teams->archive($team);

        return $this->ok(new TeamResource($team), "Team “{$team->name}” archived.");
    }

    public function restore(Team $team): JsonResponse
    {
        $this->teams->restore($team);

        return $this->ok(new TeamResource($team), "Team “{$team->name}” restored.");
    }
}

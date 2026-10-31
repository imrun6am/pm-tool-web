<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CompleteReleaseRequest;
use App\Http\Requests\ReleaseRequest;
use App\Http\Resources\V1\ReleaseResource;
use App\Http\Resources\V1\ReleaseSummaryResource;
use App\Models\Release;
use App\Services\ReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Release planning over HTTP.
 *
 * The filter chain, the phase/off-day/member reconciliation, and the overlap
 * rule all live in ReleaseService, shared with the Blade controller. What is
 * specific to this layer is how the overlap warning is delivered: the web app
 * flashes it to the session, while here it rides beside `data` as a `warning`
 * object — same rule, same message, two presentations.
 */
class ReleaseController extends ApiController
{
    public function __construct(private readonly ReleaseService $releases) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->releases
            ->filtered([
                'status' => $request->input('status'),
                'project_id' => $this->filterId($request, 'project_id'),
                'team_id' => $this->filterId($request, 'team_id'),
                'year' => $this->filterId($request, 'year'),
                'search' => $request->filled('search') ? $request->string('search')->toString() : null,
            ])
            ->withCount(['tasks', 'documents', 'comments', 'offDays', 'members']);

        return $this->paginate($request, $query, ReleaseResource::class);
    }

    /**
     * A release with everything its detail screen needs. Meeting notes and
     * quick links are scoped to the viewer, so a record they may not read never
     * rides along in the payload.
     */
    public function show(Request $request, Release $release): JsonResponse
    {
        $release->load([
            'project', 'team', 'phases', 'completedBy', 'members',
            'documents.uploader', 'offDays', 'comments.user',
            'rootTasks.assignee', 'rootTasks.subtasks.assignee',
        ])->loadCount(['tasks', 'documents', 'comments', 'offDays', 'members']);

        $release->setRelation('meetingNotes', $release->meetingNotes()
            ->with('author')->visibleTo($request->user())->get());

        $release->setRelation('quickLinks', $release->quickLinks()
            ->with('author')->visibleTo($request->user())->get());

        return $this->ok(
            (new ReleaseResource($release))->additional([
                'conflicts' => ReleaseSummaryResource::collection(
                    $this->releases->conflictsFor($release)
                )->resolve($request),
            ])
        );
    }

    public function store(ReleaseRequest $request): JsonResponse
    {
        $release = $this->releases->create(
            $request->validated(),
            $request->input('phases', []),
            $request->input('off_days', []),
            $request->input('members', []),
        );

        return $this->created(
            $this->hydrate($release)->additional($this->overlapWarning($request, $release)),
            "Release “{$release->name}” created."
        );
    }

    public function update(ReleaseRequest $request, Release $release): JsonResponse
    {
        $this->releases->update(
            $release,
            $request->validated(),
            $request->input('phases', []),
            $request->input('off_days', []),
            $request->input('members', []),
        );

        return $this->ok(
            $this->hydrate($release)->additional($this->overlapWarning($request, $release)),
            "Release “{$release->name}” updated."
        );
    }

    public function destroy(Release $release): JsonResponse
    {
        $name = $release->name;
        $release->delete(); // cascades phases/documents; the model event removes stored files

        return $this->message("Release “{$name}” deleted.");
    }

    public function complete(CompleteReleaseRequest $request, Release $release): JsonResponse
    {
        $this->releases->complete($release, $request->user(), $request->input('completion_notes'));

        return $this->ok($this->hydrate($release), "Release “{$release->name}” marked complete.");
    }

    public function reopen(Release $release): JsonResponse
    {
        $this->releases->reopen($release);

        return $this->ok($this->hydrate($release), "Release “{$release->name}” reopened.");
    }

    /** The other releases this one double-books its team against. */
    public function conflicts(Release $release): JsonResponse
    {
        return $this->ok(ReleaseSummaryResource::collection($this->releases->conflictsFor($release)));
    }

    /** The relation set every single-release response returns. */
    private function hydrate(Release $release): ReleaseResource
    {
        return new ReleaseResource(
            $release->load(['project', 'team', 'phases', 'members', 'offDays', 'completedBy'])
                ->loadCount(['tasks', 'documents', 'comments', 'offDays', 'members'])
        );
    }

    /**
     * The overlap payload attached beside `data`, or an empty array when the
     * team is free — so the `warning` key is absent rather than null.
     *
     * @return array<string, mixed>
     */
    private function overlapWarning(Request $request, Release $release): array
    {
        $conflicts = $this->releases->conflictsFor($release);
        $message = $this->releases->overlapMessage($release, $conflicts);

        if ($message === null) {
            return [];
        }

        return [
            'warning' => [
                'type' => 'team_overlap',
                'message' => $message,
                'conflicts' => ReleaseSummaryResource::collection($conflicts)->resolve($request),
            ],
        ];
    }
}

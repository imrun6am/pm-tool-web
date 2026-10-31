<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\QuickLinkRequest;
use App\Http\Resources\V1\QuickLinkResource;
use App\Models\QuickLink;
use App\Services\QuickLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saved bookmarks. A quick link belongs to its author — QuickLinkPolicy lets
 * nobody else edit or delete one, leads included.
 */
class QuickLinkController extends ApiController
{
    public function __construct(private readonly QuickLinkService $quickLinks) {}

    /**
     * The caller's visible links, split into their own and everyone else's
     * shared ones — the same partition the web drawer renders.
     */
    public function index(Request $request): JsonResponse
    {
        $partitioned = $this->quickLinks->partitionedFor(
            $request->user(),
            $this->filterId($request, 'release_id'),
        );

        return $this->ok([
            'mine' => QuickLinkResource::collection($partitioned['mine'])->resolve($request),
            'shared' => QuickLinkResource::collection($partitioned['shared'])->resolve($request),
        ]);
    }

    /**
     * Toggle the caller's pin. Authorized by visibility, not ownership — a
     * shared link may be pinned by anyone who can see it. Returns the
     * resulting state so the client never has to guess which way it went.
     */
    public function pin(Request $request, QuickLink $quickLink): JsonResponse
    {
        $this->authorize('pin', $quickLink);

        $pinned = $this->quickLinks->togglePin($quickLink, $request->user());

        return $this->ok(
            ['id' => $quickLink->id, 'is_pinned' => $pinned],
            $pinned ? 'Link pinned.' : 'Link unpinned.'
        );
    }

    public function store(QuickLinkRequest $request): JsonResponse
    {
        $link = $this->quickLinks->create($request->validated(), $request->user());

        return $this->created(new QuickLinkResource($link->load(['author', 'release'])), 'Link added.');
    }

    public function update(QuickLinkRequest $request, QuickLink $quickLink): JsonResponse
    {
        $this->authorize('update', $quickLink);

        $this->quickLinks->update($quickLink, $request->validated());

        return $this->ok(new QuickLinkResource($quickLink->load(['author', 'release'])), 'Link updated.');
    }

    public function destroy(QuickLink $quickLink): JsonResponse
    {
        $this->authorize('delete', $quickLink);

        $quickLink->delete();

        return $this->message('Link deleted.');
    }
}

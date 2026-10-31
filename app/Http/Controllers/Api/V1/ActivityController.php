<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\ActivityResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\Activity;
use App\Services\ActivityInsights;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * The app-wide audit feed, restricted to full-access roles.
 *
 * Filters and roll-ups live in ActivityInsights, shared with the Blade feed.
 * Performance scores never appear here — see the service's note on why.
 */
class ActivityController extends ApiController
{
    public function __construct(private readonly ActivityInsights $insights) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = $this->insights->feed([
            'causer_id' => $this->filterId($request, 'causer_id'),
            'release_id' => $this->filterId($request, 'release_id'),
            'event' => $request->input('event'),
            'subject_type' => $request->input('subject_type'),
            'from' => $request->filled('from')
                ? Carbon::parse($request->input('from'))->toDateString()
                : null,
            'to' => $request->filled('to')
                ? Carbon::parse($request->input('to'))->toDateString()
                : null,
        ]);

        return $this->paginate($request, $query, ActivityResource::class);
    }

    /**
     * Headline counts, a fortnight of daily volume, the top contributors, and
     * the busiest subject types — optionally focused on one user.
     */
    public function stats(Request $request): JsonResponse
    {
        $causerId = $this->filterId($request, 'causer_id');

        return $this->ok([
            'totals' => $this->insights->totals($causerId),
            'trend' => array_map(fn (array $day) => [
                'date' => $day['date']->toDateString(),
                'count' => $day['count'],
            ], $this->insights->trend($causerId)),
            'top_contributors' => $this->insights->topContributors($causerId)
                ->map(fn (Activity $row) => [
                    'user' => $row->causer
                        ? (new UserSummaryResource($row->causer))->resolve($request)
                        : null,
                    'count' => (int) $row->c,
                ])->values(),
            'by_subject_type' => $this->insights->bySubjectType($causerId),
        ]);
    }
}

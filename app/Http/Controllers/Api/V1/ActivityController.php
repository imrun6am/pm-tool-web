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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'team_id' => $this->filterId($request, 'team_id'),
            'project_id' => $this->filterId($request, 'project_id'),
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

    /**
     * The analysis page's roll-ups for one scope and period: volume against
     * the previous period, breakdowns by event/type/release/team/person, the
     * weekday × hour rhythm, task workflow moves and dwell times, and recent
     * deletions. Same filters and numbers as the web page.
     */
    public function analysis(Request $request): JsonResponse
    {
        $request->validate([
            'days' => ['nullable', Rule::in(array_keys(ActivityInsights::PERIODS))],
            'causer_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'release_id' => ['nullable', 'integer'],
        ]);

        $a = $this->insights->analysis(ActivityInsights::filters($request->query()));
        $snake = fn (array $values) => collect($values)->mapWithKeys(fn ($v, $k) => [Str::snake($k) => $v])->all();
        $w = $a['window'];

        return $this->ok([
            'window' => [
                'days' => $w['days'],
                'label' => $w['label'],
                'start' => $w['start']->toDateString(),
                'end' => $w['end']->toDateString(),
                'previous_start' => $w['previousStart']->toDateString(),
                'previous_end' => $w['previousEnd']->toDateString(),
            ],
            'kpis' => $snake($a['kpis']),
            'volume' => array_map(fn (array $b) => [
                'start' => $b['start']->toDateString(),
                'end' => $b['end']->toDateString(),
                'label' => $b['label'],
                'count' => $b['count'],
                'is_current' => $b['current'],
            ], $a['volume']),
            'by_event' => $a['byEvent'],
            'by_type' => $a['byType'],
            'by_release' => $a['byRelease'],
            'by_team' => $a['byTeam'],
            'people' => array_map(fn (array $p) => [
                'user' => (new UserSummaryResource($p['user']))->resolve($request),
                ...collect($p)->except('user')->all(),
            ], $a['people']),
            'rhythm' => $a['rhythm'],
            'flow' => [
                'moves' => $a['flow']['moves'],
                'milestones' => $a['flow']['milestones'],
                'rework_rate' => $a['flow']['reworkRate'],
                'transitions' => $a['flow']['transitions'],
                'dwell' => array_map($snake, $a['flow']['dwell']),
            ],
            'deletions' => ActivityResource::collection($a['deletions'])->resolve($request),
        ]);
    }
}

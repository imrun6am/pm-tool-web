<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TasksheetEntryRequest;
use App\Http\Resources\V1\TasksheetEntryResource;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\User;
use App\Services\TasksheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * The team's daily tasksheet.
 *
 * The roster rule (who belongs on a given day's sheet), the output trend, and
 * the lead-only `feedback` write all live in TasksheetService, shared with the
 * Blade tasksheet. The resource omits `feedback` for a non-lead reader, and the
 * service ignores it on a non-lead write — read and write are guarded
 * independently, so neither alone is load-bearing.
 */
class TasksheetController extends ApiController
{
    public function __construct(private readonly TasksheetService $tasksheet) {}

    /** The day grid for one team: member rows, their entries, and the trend. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'date' => ['nullable', 'date'],
        ]);

        $viewer = $request->user();

        $teams = $this->tasksheet->teamsFor($viewer);
        $team = $this->tasksheet->resolveTeam($teams, $this->filterId($request, 'team_id'));

        $day = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : today();

        $entries = collect();
        $rowUsers = collect();
        $viewerIsMember = false;
        $trend = [];
        $filters = $this->tasksheet->parseFilters($request->only(['attendance', 'fill', 'leave', 'member']));

        if ($team) {
            $entries = $this->tasksheet->entriesFor($team, $day);
            $viewerIsMember = $team->members()->whereKey($viewer->id)->exists();
            $trend = $this->tasksheet->trend($team, $day);

            // Same collection-level filtering as the Blade sheet, through the
            // same predicate — a member with no row is still a row here.
            $rowUsers = $this->tasksheet->rowUsersFor($team, $day, $entries)->filter(
                fn (User $u) => $this->tasksheet->rowMatches($entries->get($u->id), $u->id, $filters)
            )->values();
        }

        return $this->ok([
            'teams' => TeamSummaryResource::collection($teams)->resolve($request),
            'team' => $team ? (new TeamSummaryResource($team))->resolve($request) : null,
            'date' => $day->toDateString(),
            'is_today' => $day->isToday(),
            'is_past' => $day->lt(today()),
            'viewer_is_member' => $viewerIsMember,
            'can_write_feedback' => $viewer->isLead(),
            'rows' => $rowUsers->map(fn (User $u) => [
                'user' => (new UserSummaryResource($u))->resolve($request),
                'entry' => ($entry = $entries->get($u->id))
                    ? (new TasksheetEntryResource($entry))->resolve($request)
                    : null,
            ])->values()->all(),
            'trend' => $this->presentTrend($trend),
        ]);
    }

    /**
     * One member's history. Readable by that member and by leads only — a
     * teammate's daily log is not open reading.
     */
    public function user(Request $request, User $member): AnonymousResourceCollection
    {
        $viewer = $request->user();
        abort_unless($viewer->isLead() || $viewer->id === $member->id, 403);

        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->toDateString() : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $query = $this->tasksheet->applyHistoryFilters(
            $this->tasksheet->history($member, $this->filterId($request, 'team_id'), $from, $to),
            $this->tasksheet->parseFilters($request->only(['attendance', 'fill', 'leave'])),
        );

        return $this->paginate($request, $query, TasksheetEntryResource::class);
    }

    /**
     * Record standup attendance. Leads only, via the verifyStandup policy —
     * `update` would not do, since it also grants a member their own row.
     */
    public function verifyStandup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'attended' => ['nullable', 'boolean'],
        ]);

        $this->authorize('verifyStandup', $this->tasksheet->resolveEntry($data));

        $raw = $request->input('attended');

        $entry = $this->tasksheet->setStandupAttendance(
            $data,
            $raw === null || $raw === '' ? null : $request->boolean('attended'),
            $request->user(),
        );

        return $this->ok(
            new TasksheetEntryResource($entry->load(['member', 'team'])),
            'Standup attendance saved.'
        );
    }

    /**
     * Retired. This rendered the whole PDF inside the request, which a wide
     * range could not survive; exports are now queued (TasksheetExportController).
     * Answering 410 rather than queueing one keeps a GET free of side effects,
     * so a retried or prefetched request cannot start real work.
     */
    public function report(): JsonResponse
    {
        $successor = route('api-v1.tasksheet.exports.store');

        return response()->json([
            'message' => 'This endpoint has been retired. Create a background export with POST /api/v1/tasksheet/exports, poll it, then download the PDF.',
            'successor' => ['method' => 'POST', 'url' => $successor],
        ], 410)->header('Link', '<'.$successor.'>; rel="successor-version"');
    }

    /** Save one member's row for one date. */
    public function upsert(TasksheetEntryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $entry = $this->tasksheet->resolveEntry($data);
        $this->authorize('update', $entry);

        $this->tasksheet->save($entry, $data, $request->user(), $request->has('feedback'));

        return $this->ok(
            new TasksheetEntryResource($entry->load(['member', 'team'])),
            'Tasksheet saved.'
        );
    }

    /**
     * The shared trend uses the keys the Blade chart reads (`wp`, `current`);
     * this API's published contract names them `work_points` and `is_current`.
     *
     * @param  list<array<string, mixed>>  $trend
     * @return list<array<string, mixed>>
     */
    private function presentTrend(array $trend): array
    {
        return array_map(fn (array $day) => [
            'date' => $day['date'],
            'label' => $day['label'],
            'dow' => $day['dow'],
            'work_points' => $day['wp'],
            'is_current' => $day['current'],
        ], $trend);
    }
}

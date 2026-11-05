<?php

namespace App\Http\Controllers;

use App\Http\Requests\TasksheetBoardRequest;
use App\Http\Requests\TasksheetEntryRequest;
use App\Models\Release;
use App\Models\TasksheetExport;
use App\Models\User;
use App\Services\Board\PlanToBoardService;
use App\Services\TasksheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TasksheetController extends Controller
{
    public function __construct(private readonly TasksheetService $tasksheet) {}

    public function index(): View
    {
        $viewer = request()->user();

        $teams = $this->tasksheet->teamsFor($viewer);
        $team = $this->tasksheet->resolveTeam($teams, request()->integer('team') ?: null);

        $day = ($d = request('date')) ? Carbon::parse($d)->startOfDay() : today();

        $entries = collect();
        $boardReleases = collect();
        $rowUsers = collect();
        $allRowUsers = collect();
        $viewerIsMember = false;
        $trend = [];
        $loggedMinutes = collect();
        $openTasks = collect();
        $filters = $this->tasksheet->parseFilters(request()->only(['attendance', 'fill', 'leave', 'member']));

        if ($team) {
            $entries = $this->tasksheet->entriesFor($team, $day);
            $allRowUsers = $this->tasksheet->rowUsersFor($team, $day, $entries);
            $viewerIsMember = $team->members()->whereKey($viewer->id)->exists();
            $trend = $this->tasksheet->trend($team, $day);
            // "Add to board" targets one of the team's open releases.
            $boardReleases = Release::ongoing()->where('team_id', $team->id)->orderBy('start_date')->get();

            // Filter the assembled rows, not the query: a member with no row at
            // all still belongs on the sheet, and is exactly what the "empty"
            // and "unmarked" filters are for.
            $rowUsers = $allRowUsers->filter(
                fn (User $u) => $this->tasksheet->rowMatches($entries->get($u->id), $u->id, $filters)
            )->values();

            // Hours name people, so a viewer doesn't get them (see TaskTimeLogPolicy).
            $loggedMinutes = $viewer->isViewer() ? collect() : $this->tasksheet->loggedMinutes($team, $day);
            // Only rows the viewer can edit have a plan to add to.
            $openTasks = $this->tasksheet->openTasksFor(
                $viewer->isLead() ? $rowUsers->pluck('id')->all() : ($viewerIsMember ? [$viewer->id] : [])
            );
        }

        return view('tasksheet.index', [
            'teams' => $teams,
            'team' => $team,
            'day' => $day,
            'prev' => $day->copy()->subDay(),
            'next' => $day->copy()->addDay(),
            'isToday' => $day->isToday(),
            'isPast' => $day->lt(today()),
            'rowUsers' => $rowUsers,
            // The unfiltered roster still drives the stat tiles and the member
            // filter's options, so narrowing the list never rewrites the totals.
            'allRowUsers' => $allRowUsers,
            'entries' => $entries,
            'viewerIsMember' => $viewerIsMember,
            'trend' => $trend,
            'filters' => $filters,
            'hasFilters' => $this->tasksheet->hasFilters($filters),
            'boardReleases' => $boardReleases,
            'loggedMinutes' => $loggedMinutes,
            'openTasks' => $openTasks,
        ]);
    }

    /** Per-user tasksheet history, filterable by team and date range. */
    public function user(User $member): View
    {
        $viewer = request()->user();
        abort_unless($viewer->isLead() || $viewer->id === $member->id, 403);

        $teamFilter = request()->integer('team') ?: null;
        $from = ($f = request('from')) ? Carbon::parse($f)->toDateString() : null;
        $to = ($t = request('to')) ? Carbon::parse($t)->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $filters = $this->tasksheet->parseFilters(request()->only(['attendance', 'fill', 'leave']));

        return view('tasksheet.user', [
            'member' => $member,
            'entries' => $this->tasksheet->applyHistoryFilters(
                $this->tasksheet->history($member, $teamFilter, $from, $to),
                $filters,
            )->get(),
            'teams' => $this->tasksheet->teamsWithHistory($member),
            'teamFilter' => $teamFilter,
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'hasFilters' => $this->tasksheet->hasFilters($filters) || $teamFilter || $from || $to,
        ]);
    }

    /**
     * The old inline PDF URL, still linked from KPI review spreadsheets.
     *
     * Rendering here is what took workers down on wide ranges, so it now
     * applies the same rule as before — team-wide is lead-only, a member may
     * export only their own rows — and hands the selection to the exports
     * page prefilled. A GET never creates the export itself.
     */
    public function report(Request $request): RedirectResponse
    {
        $memberId = $request->filled('member') ? $request->integer('member') : null;
        $this->authorize('create', [TasksheetExport::class, $memberId]);

        return redirect()->route('tasksheet.exports.index', array_filter(
            $request->only(TasksheetExportController::INPUT),
            fn ($value) => filled($value),
        ));
    }

    /**
     * Record standup attendance from the list, without touching the row's task
     * content. Separate from `upsert` on purpose: that is the member's form and
     * carries every task field, so routing a lead's click through it could
     * clobber work in progress.
     */
    public function verifyStandup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'attended' => ['nullable', 'boolean'],
        ]);

        $this->authorize('verifyStandup', $this->tasksheet->resolveEntry($data));

        // Absent or blank clears the mark back to "not yet marked"; the list's
        // checkbox always sends 0 or 1, so only an explicit clear reaches null.
        $raw = $request->input('attended');

        $this->tasksheet->setStandupAttendance(
            $data,
            $raw === null || $raw === '' ? null : $request->boolean('attended'),
            $request->user(),
        );

        return back()->with('success', 'Standup attendance saved.');
    }

    public function upsert(TasksheetEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $entry = $this->tasksheet->resolveEntry($data);
        $this->authorize('update', $entry);

        $this->tasksheet->save($entry, $data, $request->user(), $request->has('feedback'));

        return redirect()->route('tasksheet.index', [
            'team' => $entry->team_id,
            'date' => $entry->date->toDateString(),
        ])->with('success', 'Tasksheet saved.');
    }

    /**
     * "Add to board": save the row exactly like upsert(), then let the AI split
     * the plan into To Do cards and subtasks assigned to the row's member. The row
     * is saved even when the AI is unavailable — only the board step is skipped.
     */
    public function addToBoard(TasksheetBoardRequest $request, PlanToBoardService $board): RedirectResponse
    {
        $data = $request->validated();

        $entry = $this->tasksheet->resolveEntry($data);
        $this->authorize('update', $entry);

        $this->tasksheet->save($entry, $data, $request->user(), $request->has('feedback'));
        $entry->refresh()->load('member');

        $back = redirect()->route('tasksheet.index', [
            'team' => $entry->team_id,
            'date' => $entry->date->toDateString(),
        ]);

        $release = isset($data['board_release_id']) ? Release::findOrFail($data['board_release_id']) : null;
        $result = $board->attempt($entry, $release, $request->user());

        return $back->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}

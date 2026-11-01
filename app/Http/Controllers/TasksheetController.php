<?php

namespace App\Http\Controllers;

use App\Http\Requests\TasksheetBoardRequest;
use App\Http\Requests\TasksheetEntryRequest;
use App\Models\Release;
use App\Models\User;
use App\Services\Board\PlanToBoardService;
use App\Services\Kpi\MissingApiKeyException;
use App\Services\TasksheetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

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
     * A tasksheet PDF for a day, a range, or one member.
     *
     * Team-wide is lead-only; a member may export only their own rows — the
     * same rule the per-user page applies. Crucially, whether `feedback` is
     * included is decided from the **requesting user**, not from whose rows
     * these are: it is the lead's private note *about* a member, and the export
     * must not become the read path the screen already denies them.
     */
    public function report(Request $request): Response
    {
        $viewer = $request->user();
        $teamId = $request->filled('team') ? $request->integer('team') : null;
        $memberId = $request->filled('member') ? $request->integer('member') : null;

        abort_unless($viewer->isLead() || $memberId === $viewer->id, 403);

        [$from, $to] = $this->tasksheet->reportRange($request->only(['date', 'from', 'to']));

        $data = $this->tasksheet->reportData(
            $teamId, $memberId, $from, $to,
            $this->tasksheet->parseFilters($request->only(['attendance', 'fill', 'leave'])),
            $viewer,
        );

        return Pdf::loadView('tasksheet.report', $data)
            ->setPaper('a4', 'landscape')
            ->download($this->tasksheet->reportFilename($data));
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
     * "Add to board": save the row exactly like upsert(), then let Claude split
     * the plan into To Do cards assigned to the row's member. The row is saved
     * even when Claude is unavailable — only the board step is skipped.
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

        try {
            $result = $board->addPlanTasks($entry, Release::findOrFail($data['board_release_id']), $request->user());
        } catch (MissingApiKeyException) {
            return $back->with('error', 'Tasksheet saved, but no tasks were added: Claude isn’t configured (ANTHROPIC_API_KEY).');
        } catch (Throwable $e) {
            report($e);

            return $back->with('error', 'Tasksheet saved, but no tasks were added: Claude couldn’t split the plan right now. Please try again.');
        }

        $created = count($result['created']);
        $name = $entry->member->name;
        $message = match (true) {
            $created > 0 => "Tasksheet saved. Added {$created} ".Str::plural('task', $created)." to the board for {$name}.",
            $result['skipped'] > 0 => "Tasksheet saved. Those tasks are already on the board for {$name}.",
            default => 'Tasksheet saved. Claude found no actionable tasks in the plan.',
        };
        if ($created > 0 && $result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} already on the board.";
        }

        return $back->with('success', $message);
    }
}

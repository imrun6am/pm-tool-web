<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClaudeHabitReport;
use App\Models\ClaudeDeviceOwner;
use App\Models\ClaudeHabitReport;
use App\Models\User;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\ClaudeHabitAnalyst;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Claude Code habit reports: an AI review of one person's prompts and sessions
 * over a period, from the devices linked to them. Same viewers as the Claude
 * activity log.
 */
class ClaudeHabitReportController extends Controller
{
    public function __construct(private readonly ClaudeHabitAnalyst $analyst) {}

    /** GET /claude-activity/habits — who can be reviewed, and past reports. */
    public function index(Request $request, AiFeatures $ai): View
    {
        abort_unless(ClaudeActivityController::canView($request->user()), 403);

        $people = User::query()
            ->whereIn('id', ClaudeDeviceOwner::select('user_id'))
            ->withCount('claudeDevices')
            ->orderBy('name')
            ->get(['id', 'name']);

        $reports = ClaudeHabitReport::with(['user:id,name', 'requester:id,name'])
            ->latest('id')
            ->paginate(20);

        return view('claude-activity.habits.index', [
            'people' => $people,
            'reports' => $reports,
            'running' => $reports->getCollection()->contains(fn (ClaudeHabitReport $r) => $r->running()),
            'available' => $ai->available(ClaudeHabitAnalyst::FEATURE),
        ]);
    }

    /** POST /claude-activity/habits — queue a report for one person and period. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(ClaudeActivityController::canView($request->user()), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $person = User::findOrFail($data['user_id']);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();

        if ($this->analyst->devices($person) === []) {
            return back()->withInput()->withErrors(['user_id' => "{$person->name} has no device linked. Link one on the Claude activity page first."]);
        }
        if ($from->diffInDays($to) > 366) {
            return back()->withInput()->withErrors(['from' => 'Choose a period of a year or less.']);
        }
        if ($this->analyst->promptCount($person, $from, $to) === 0) {
            return back()->withInput()->withErrors(['from' => "{$person->name} sent no prompts from their devices in this period."]);
        }

        $report = ClaudeHabitReport::create([
            'user_id' => $person->id,
            'requested_by' => $request->user()->id,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'status' => ClaudeHabitReport::STATUS_QUEUED,
        ]);

        GenerateClaudeHabitReport::dispatch($report->id);

        return redirect()->route('claude-activity.habits.index')
            ->with('success', "Writing {$person->name}’s habit report. It shows below when it’s ready.");
    }

    /** GET /claude-activity/habits/{report} */
    public function show(Request $request, ClaudeHabitReport $report): View
    {
        abort_unless(ClaudeActivityController::canView($request->user()), 403);

        $report->load(['user:id,name', 'requester:id,name']);

        // Reports written before projects and days had their detail counted get it from the log.
        $projects = $report->facts['projects'] ?? [];
        $days = $report->facts['days'] ?? null;
        if ($report->status === ClaudeHabitReport::STATUS_COMPLETED && (! isset($projects[0]['folders']) || $days === null)) {
            $detail = $this->analyst->detailFor($report);
            $projects = isset($projects[0]['folders']) ? $projects : $detail['projects'];
            $days ??= $detail['days'];
        }

        return view('claude-activity.habits.show', ['report' => $report, 'projects' => $projects, 'days' => $days ?? []]);
    }

    /** DELETE /claude-activity/habits/{report} */
    public function destroy(Request $request, ClaudeHabitReport $report): RedirectResponse
    {
        abort_unless(ClaudeActivityController::canView($request->user()), 403);

        $report->delete();

        return redirect()->route('claude-activity.habits.index')->with('success', 'Report deleted.');
    }
}

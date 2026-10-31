<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Project;
use App\Models\Release;
use App\Models\ReleasePhase;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\Timeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the dashboard computes, for both the Blade timeline and the API.
 *
 * The timeline is *geometry*, not a list of records: each bar carries its offset
 * and width as a percentage of the axis, and its phase segments are positioned
 * relative to the bar itself. Computing that here rather than in each client is
 * the whole point — two consumers deriving percentages independently is how two
 * surfaces end up drawing different pictures of one schedule.
 *
 * Returned structures hold models and Carbon instances, never formatted strings,
 * so the Blade views can read `$bar['release']->name` while the API maps the same
 * model through a resource.
 */
class DashboardService
{
    public function __construct(private readonly OverlapChecker $overlap) {}

    /** Releases whose window intersects the axis, plus the project/team filters. */
    public function timelineReleases(DashboardFilters $filters): Collection
    {
        return Release::query()
            ->with(['project', 'team', 'phases'])
            ->whereNull('completed_at') // a shipped release drops off the timeline
            ->whereDate('start_date', '<=', $filters->rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $filters->rangeStart->toDateString())
            ->when($filters->projectId, fn ($q) => $q->where('project_id', $filters->projectId))
            ->when($filters->teamId, fn ($q) => $q->where('team_id', $filters->teamId))
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Flag every visible release that overlaps another owned by the same team.
     *
     * Conflicts are computed across each team's *entire* schedule, not just the
     * filtered view — otherwise a bar would look clear merely because the
     * release it collides with is off-screen.
     *
     * @param  Collection<int, Release>  $releases
     * @return array<int, bool>
     */
    public function conflictFlags(Collection $releases): array
    {
        $flags = [];

        foreach ($releases->pluck('team_id')->unique() as $teamId) {
            $teamReleases = Release::where('team_id', $teamId)->whereNull('completed_at')->get();

            foreach ($this->overlap->flagConflicts($teamReleases) as $releaseId => $flag) {
                $flags[$releaseId] = $flag;
            }
        }

        return $flags;
    }

    /**
     * Group releases by team or project and attach each bar's timeline geometry.
     *
     * @param  Collection<int, Release>  $releases
     * @param  array<int, bool>  $conflicts
     * @return list<array<string, mixed>>
     */
    public function groups(Collection $releases, DashboardFilters $filters, array $conflicts): array
    {
        $byProject = $filters->groupsByProject();

        $grouped = $releases->groupBy(fn (Release $r) => $byProject ? $r->project_id : $r->team_id);

        $groups = [];

        foreach ($grouped as $items) {
            $owner = $byProject ? $items->first()->project : $items->first()->team;

            $bars = [];

            foreach ($items as $release) {
                $seg = Timeline::segment(
                    $release->start_date,
                    $release->end_date,
                    $filters->rangeStart,
                    $filters->rangeEnd
                );

                if (! $seg['visible']) {
                    continue;
                }

                $bars[] = [
                    'release' => $release,
                    'offset' => $seg['offset'],
                    'width' => $seg['width'],
                    'conflict' => $conflicts[$release->id] ?? false,
                    'phases' => $this->phaseSegments($release),
                ];
            }

            if ($bars === []) {
                continue;
            }

            $groups[] = [
                'id' => $owner->id,
                'type' => $filters->groupBy,
                'label' => $owner->name,
                'color' => $owner->color,
                'bars' => $bars,
            ];
        }

        usort($groups, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $groups;
    }

    /** The month columns of the axis. */
    public function months(DashboardFilters $filters): array
    {
        return Timeline::monthColumns($filters->rangeStart, $filters->rangeEnd);
    }

    /**
     * Headline metrics and chart series, scoped to the same range and filters as
     * the timeline so the numbers and the bars always agree.
     *
     * @param  Collection<int, Release>  $timelineReleases
     * @param  array<int, bool>  $conflicts
     * @return array<string, mixed>
     */
    public function analytics(DashboardFilters $filters, Collection $timelineReleases, array $conflicts): array
    {
        $rangeReleases = Release::query()
            ->with('team:id,name,color')
            ->whereDate('start_date', '<=', $filters->rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $filters->rangeStart->toDateString())
            ->when($filters->projectId, fn ($q) => $q->where('project_id', $filters->projectId))
            ->when($filters->teamId, fn ($q) => $q->where('team_id', $filters->teamId))
            ->get();

        $ongoing = $rangeReleases->whereNull('completed_at');
        $monthly = $this->monthlyLoad($rangeReleases, $filters);
        $statusCounts = $this->statusCounts($ongoing->pluck('id'));
        $taskTotal = array_sum($statusCounts);
        $teamWorkload = $this->teamWorkload($ongoing);
        $conflictedIds = array_keys(array_filter($conflicts));

        return [
            'year' => $filters->year,
            'periodLabel' => $filters->periodLabel(),
            'active' => $ongoing->count(),
            'completedThisYear' => $this->completedInRange($filters),
            'upcoming' => $this->startingSoon($filters),
            'conflictCount' => count($conflictedIds),
            'teamsDoubleBooked' => $timelineReleases
                ->whereIn('id', $conflictedIds)
                ->pluck('team_id')->unique()->count(),
            'monthly' => $monthly,
            'monthlyMax' => max(array_column($monthly, 'count') ?: [0]),
            'statusCounts' => $statusCounts,
            'statusLabels' => Task::STATUSES,
            'taskTotal' => $taskTotal,
            'donePct' => $taskTotal > 0
                ? (int) round(($statusCounts['done'] + $statusCounts['archive']) / $taskTotal * 100)
                : 0,
            'teamWorkload' => $teamWorkload,
            'teamWorkloadMax' => collect($teamWorkload)->max('count') ?: 0,
        ];
    }

    /** Every year that has releases, always spanning the selection and today. */
    public function availableYears(int $selected): array
    {
        $min = (int) (Release::min('year') ?? $selected);
        $max = (int) (Release::max('year') ?? $selected);

        return range(
            min($min, $selected, (int) now()->year),
            max($max, $selected, (int) now()->year)
        );
    }

    /** The filter pickers the dashboard offers. */
    public function filterOptions(): array
    {
        return [
            'projects' => Project::orderBy('name')->get(),
            'teams' => Team::orderBy('name')->get(),
        ];
    }

    /**
     * The personal "what am I doing today" dashboard developers and QA get
     * instead of the planning timeline.
     *
     * @return array<string, mixed>
     */
    public function memberSnapshot(User $user): array
    {
        // Open tasks assigned to me — most urgent first, undated last.
        $tasks = Task::with('release')
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $teams = $user->teams()->orderBy('name')->get();

        $sheetEntries = TasksheetEntry::where('user_id', $user->id)
            ->whereIn('team_id', $teams->pluck('id'))
            ->whereDate('date', today()->toDateString())
            ->get()
            ->keyBy('team_id');

        // Meetings I'm attending (or created) in the next fortnight.
        $meetings = Event::with(['release', 'creator'])
            ->where('type', 'meeting')
            ->whereBetween('starts_at', [now(), now()->addDays(14)])
            ->where(fn ($q) => $q
                ->whereHas('attendees', fn ($a) => $a->whereKey($user->id))
                ->orWhere('created_by', $user->id))
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        return [
            'tasks' => $tasks,
            'teams' => $teams,
            'sheetEntries' => $sheetEntries,
            'meetings' => $meetings,
            'overdueCount' => $tasks->filter(
                fn (Task $t) => $t->due_date && $t->due_date->lt(today())
            )->count(),
        ];
    }

    /**
     * A release's phase segments, positioned as percentages of the release bar
     * rather than of the axis.
     *
     * @return list<array<string, mixed>>
     */
    private function phaseSegments(Release $release): array
    {
        return $release->phases->map(function (ReleasePhase $phase) use ($release) {
            $rel = Timeline::relativeSegment(
                $phase->start_date,
                $phase->end_date,
                $release->start_date,
                $release->end_date
            );

            return [
                'phase' => $phase->phase,
                'label' => $phase->label(),
                'offset' => $rel['offset'],
                'width' => $rel['width'],
                'color' => Release::PHASE_COLORS[$phase->phase] ?? '#94a3b8',
                'start' => $phase->start_date,
                'end' => $phase->end_date,
            ];
        })->values()->all();
    }

    /**
     * How many releases are in flight during each month the range spans.
     *
     * @param  Collection<int, Release>  $rangeReleases
     * @return list<array<string, mixed>>
     */
    private function monthlyLoad(Collection $rangeReleases, DashboardFilters $filters): array
    {
        $monthly = [];
        $cursor = $filters->rangeStart->copy()->startOfMonth();
        $lastMonth = $filters->rangeEnd->copy()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $monthly[] = [
                'label' => $monthStart->format('M'),
                'count' => $rangeReleases->filter(
                    fn (Release $r) => $r->start_date <= $monthEnd && $r->end_date >= $monthStart
                )->count(),
                'current' => $monthStart->month === (int) now()->month
                    && $monthStart->year === (int) now()->year,
            ];

            $cursor->addMonth();
        }

        return $monthly;
    }

    /**
     * The task-status mix across a set of releases, with every status present
     * even when it holds nothing.
     *
     * @param  Collection<int, int>  $releaseIds
     * @return array<string, int>
     */
    private function statusCounts(Collection $releaseIds): array
    {
        $raw = Task::query()
            ->whereIn('release_id', $releaseIds)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [];
        foreach (array_keys(Task::STATUSES) as $status) {
            $counts[$status] = (int) ($raw[$status] ?? 0);
        }

        return $counts;
    }

    /**
     * Active releases owned by each team, busiest first.
     *
     * @param  Collection<int, Release>  $ongoing
     * @return list<array<string, mixed>>
     */
    private function teamWorkload(Collection $ongoing): array
    {
        return $ongoing
            ->groupBy('team_id')
            ->map(fn ($rs) => [
                'name' => $rs->first()->team->name,
                'color' => $rs->first()->team->color,
                'count' => $rs->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function completedInRange(DashboardFilters $filters): int
    {
        return Release::completed()
            ->whereDate('completed_at', '>=', $filters->rangeStart->toDateString())
            ->whereDate('completed_at', '<=', $filters->rangeEnd->toDateString())
            ->when($filters->projectId, fn ($q) => $q->where('project_id', $filters->projectId))
            ->when($filters->teamId, fn ($q) => $q->where('team_id', $filters->teamId))
            ->count();
    }

    /** Ongoing releases starting within the next 30 days. */
    private function startingSoon(DashboardFilters $filters): int
    {
        return Release::ongoing()
            ->whereDate('start_date', '>=', now()->toDateString())
            ->whereDate('start_date', '<=', now()->addDays(30)->toDateString())
            ->when($filters->projectId, fn ($q) => $q->where('project_id', $filters->projectId))
            ->when($filters->teamId, fn ($q) => $q->where('team_id', $filters->teamId))
            ->count();
    }
}

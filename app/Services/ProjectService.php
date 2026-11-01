<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Projects, and the roll-ups the project detail page charts.
 *
 * A project that owns releases is archived, never hard-deleted — deleting it
 * would cascade away every release under it.
 */
class ProjectService
{
    /** Index orderings (`?sort=`); archived projects always sink below active ones. */
    public const SORTS = [
        'name' => 'Name',
        'releases' => 'Most releases',
        'progress' => 'Task progress',
        'recent' => 'Most recent',
    ];

    /**
     * Fills for the task-status mix, keyed by status. Chart data, like
     * Release::PHASE_COLORS: Recheck is orange-700 so it stays apart from
     * In review's amber (CVD-checked, as on the member dashboard).
     *
     * @var array<string, string>
     */
    public const STATUS_FILL = [
        'todo' => '#94a3b8',        // slate-400
        'in_progress' => '#3b82f6', // blue-500
        'in_review' => '#f59e0b',   // amber-500
        'recheck' => '#c2410c',     // orange-700
        'done' => '#10b981',        // emerald-500
        'archive' => '#64748b',     // slate-500
    ];

    /**
     * @param  array{status?: ?string, search?: ?string, sort?: ?string}  $filters
     * @return Builder<Project>
     */
    public function filtered(array $filters = []): Builder
    {
        $sort = array_key_exists($filters['sort'] ?? '', self::SORTS) ? $filters['sort'] : 'name';

        $query = Project::query()
            ->withCount('releases')
            ->when(($filters['status'] ?? null) === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderByRaw('archived_at is not null'); // active first

        return match ($sort) {
            'releases' => $query->orderByDesc('releases_count')->orderBy('name'),
            'progress' => $query
                ->addSelect(['task_progress' => $this->taskProgressSubquery()])
                ->orderByDesc('task_progress')
                ->orderBy('name'),
            'recent' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderBy('name'),
        };
    }

    /**
     * Share of a project's counted tasks that are done (0–1), or -1 when it
     * has none, so task-less projects sort last under "progress".
     */
    private function taskProgressSubquery(): Builder
    {
        return Task::query()
            ->join('releases', 'releases.id', '=', 'tasks.release_id')
            ->whereColumn('releases.project_id', 'projects.id')
            ->whereNotIn('tasks.status', Task::UNCOUNTED_STATUSES)
            ->selectRaw("coalesce(sum(case when tasks.status = 'done' then 1 else 0 end) * 1.0 / nullif(count(*), 0), -1)");
    }

    /**
     * Workspace-wide counts for the index's KPI strip (ignores the filters).
     *
     * @return array{projects: int, active: int, archived: int, ongoing: int, completed: int}
     */
    public function overview(): array
    {
        $active = Project::query()->active()->count();
        $total = Project::query()->count();
        $completed = Release::query()->completed()->count();

        return [
            'projects' => $total,
            'active' => $active,
            'archived' => $total - $active,
            'ongoing' => Release::query()->ongoing()->count(),
            'completed' => $completed,
        ];
    }

    /**
     * Per-project roll-ups for the index cards, keyed by project id, from one
     * releases query: release counts, counted-task progress (archived tasks
     * never count), the teams involved, and the release to watch — the one in
     * its window now, else the next to start, else the latest past its window.
     *
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{ongoing: int, completed: int, done: int, total: int, teams: SupportCollection<int, Team>, next: ?Release}>
     */
    public function cardSummaries(Collection $projects): array
    {
        $releases = $projects->isEmpty() ? collect() : Release::query()
            ->select(['id', 'project_id', 'team_id', 'name', 'year', 'quarter', 'start_date', 'end_date', 'completed_at'])
            ->whereIn('project_id', $projects->modelKeys())
            ->with('team:id,name,color')
            ->withTaskProgress()
            ->orderBy('start_date')
            ->get()
            ->groupBy('project_id');

        $summaries = [];
        foreach ($projects as $project) {
            $own = $releases->get($project->id, collect());
            $ongoing = $own->whereNull('completed_at');

            $summaries[$project->id] = [
                'ongoing' => $ongoing->count(),
                'completed' => $own->count() - $ongoing->count(),
                'done' => (int) $own->sum('tasks_done'),
                'total' => (int) $own->sum('tasks_total'),
                'teams' => $own->pluck('team')->filter()->unique('id')->sortBy('name')->values(),
                'next' => $ongoing->first(fn (Release $r) => $r->state() === 'active')
                    ?? $ongoing->first(fn (Release $r) => $r->state() === 'upcoming')
                    ?? $ongoing->filter(fn (Release $r) => $r->state() === 'overdue')->last(),
            ];
        }

        return $summaries;
    }

    /**
     * A project's releases with their owning team and per-release task
     * roll-ups, so the detail page draws progress bars without an N+1.
     *
     * @return Collection<int, Release>
     */
    public function releasesWithProgress(Project $project): Collection
    {
        $project->load(['releases' => fn ($q) => $q
            ->with('team:id,name,color')
            // Archived tasks count toward neither the total nor the done figure.
            ->withCount([
                'tasks' => fn ($t) => $t->counted(),
                'tasks as done_tasks_count' => fn ($t) => $t->where('status', 'done'),
            ])
            ->orderBy('start_date'),
        ]);

        // Each release knows its project without a query per row (the timeline reads it).
        // A relation-free copy, so the project and its releases never form a cycle.
        $owner = $project->withoutRelations();
        $project->releases->each->setRelation('project', $owner);

        return $project->releases;
    }

    /**
     * Recent history spanning the project itself and all of its releases.
     *
     * @return Collection<int, Activity>
     */
    public function recentActivity(Project $project, Collection $releases, int $limit = 12): Collection
    {
        return Activity::query()
            ->where(fn ($q) => $q
                ->whereIn('release_id', $releases->pluck('id'))
                ->orWhere(fn ($p) => $p
                    ->where('subject_type', $project->getMorphClass())
                    ->where('subject_id', $project->getKey())))
            ->with('causer')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** A project with releases is archived rather than deleted. */
    public function isDeletable(Project $project): bool
    {
        return ! $project->releases()->exists();
    }

    public function archive(Project $project): Project
    {
        $project->update(['archived_at' => now()]);

        return $project;
    }

    public function restore(Project $project): Project
    {
        $project->update(['archived_at' => null]);

        return $project;
    }

    /**
     * Headline metrics and chart series for a single project's detail page.
     *
     * @param  Collection<int, Release>  $releases
     * @return array<string, mixed>
     */
    public function analytics(Project $project, Collection $releases): array
    {
        $releaseIds = $releases->pluck('id');
        $ongoing = $releases->whereNull('completed_at');
        $completed = $releases->whereNotNull('completed_at');

        // Task-status mix across every release in the project (one grouped query).
        // Archived tasks are left out of the mix and the total entirely.
        $raw = Task::query()
            ->counted()
            ->whereIn('release_id', $releaseIds)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = [];
        foreach (Task::countedStatuses() as $status) {
            $statusCounts[$status] = (int) ($raw[$status] ?? 0);
        }

        $taskTotal = array_sum($statusCounts);
        $doneTasks = $statusCounts['done'];

        // Open tasks already past their due date (done/archived excluded).
        $overdue = $releaseIds->isEmpty() ? 0 : Task::query()
            ->whereIn('release_id', $releaseIds)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        // Releases per owning team, busiest first.
        $byTeam = $releases
            ->groupBy('team_id')
            ->map(fn ($rs) => [
                'label' => $rs->first()->team->name,
                'value' => $rs->count(),
                'color' => $rs->first()->team->color,
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        // Release cadence: how many fall in each year·quarter, chronological.
        $byPeriod = $releases
            ->sortBy(fn ($r) => sprintf('%04d%d', $r->year, $r->quarter))
            ->groupBy(fn ($r) => $r->year.'-'.$r->quarter)
            ->map(fn ($rs) => [
                'label' => 'Q'.$rs->first()->quarter,
                'year' => $rs->first()->year,
                'count' => $rs->count(),
                'completed' => $rs->whereNotNull('completed_at')->count(),
                'current' => $rs->first()->year === (int) now()->year
                    && $rs->first()->quarter === (int) now()->quarter,
            ])
            ->values()
            ->all();

        return [
            'releaseTotal' => $releases->count(),
            'ongoing' => $ongoing->count(),
            'completed' => $completed->count(),
            'upcoming' => $ongoing->filter(fn ($r) => $r->start_date >= now()->startOfDay()
                && $r->start_date <= now()->addDays(30))->count(),
            'teamsInvolved' => $releases->pluck('team_id')->unique()->count(),
            'statusCounts' => $statusCounts,
            'statusLabels' => Task::STATUSES,
            'taskTotal' => $taskTotal,
            'doneTasks' => $doneTasks,
            'openTasks' => $taskTotal - $doneTasks,
            'overdue' => $overdue,
            'donePct' => $taskTotal > 0 ? (int) round($doneTasks / $taskTotal * 100) : 0,
            'completionPct' => $releases->count() > 0
                ? (int) round($completed->count() / $releases->count() * 100) : 0,
            'byTeam' => $byTeam,
            'byTeamMax' => collect($byTeam)->max('value') ?: 0,
            'byPeriod' => $byPeriod,
            'periodMax' => collect($byPeriod)->max('count') ?: 0,
            'spanStart' => $releases->min('start_date'),
            'spanEnd' => $releases->max('end_date'),
            // Additions for the redesigned detail page (existing keys above are API-stable).
            'statusColors' => array_intersect_key(self::STATUS_FILL, $statusCounts),
            'activeReleases' => $ongoing->filter(fn ($r) => $r->state() === 'active')->count(),
            'upcomingReleases' => $ongoing->filter(fn ($r) => $r->state() === 'upcoming')->count(),
            'overdueReleases' => $ongoing->filter(fn ($r) => $r->state() === 'overdue')->count(),
            'teamRows' => $this->teamRows($releases),
        ];
    }

    /**
     * The teams delivering a project's releases, busiest first, with each
     * team's release split and counted-task progress (needs the counts that
     * releasesWithProgress() loads).
     *
     * @param  Collection<int, Release>  $releases
     * @return array<int, array{team: Team, releases: int, ongoing: int, completed: int, done: int, total: int}>
     */
    public function teamRows(Collection $releases): array
    {
        return $releases
            ->filter(fn ($r) => $r->team)
            ->groupBy('team_id')
            ->map(fn ($rs) => [
                'team' => $rs->first()->team,
                'releases' => $rs->count(),
                'ongoing' => $rs->whereNull('completed_at')->count(),
                'completed' => $rs->whereNotNull('completed_at')->count(),
                'done' => (int) $rs->sum('done_tasks_count'),
                'total' => (int) $rs->sum('tasks_count'),
            ])
            ->sortBy([['releases', 'desc'], fn ($a, $b) => strcmp($a['team']->name, $b['team']->name)])
            ->values()
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Projects, and the roll-ups the project detail page charts.
 *
 * A project that owns releases is archived, never hard-deleted — deleting it
 * would cascade away every release under it.
 */
class ProjectService
{
    /**
     * @param  array{status?: ?string, search?: ?string}  $filters
     * @return Builder<Project>
     */
    public function filtered(array $filters = []): Builder
    {
        return Project::query()
            ->withCount('releases')
            ->when(($filters['status'] ?? null) === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderByRaw('archived_at is not null') // active first
            ->orderBy('name');
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
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($t) => $t->whereIn('status', Task::DONE_STATUSES),
            ])
            ->orderBy('start_date'),
        ]);

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
        $raw = Task::query()
            ->whereIn('release_id', $releaseIds)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = [];
        foreach (array_keys(Task::STATUSES) as $status) {
            $statusCounts[$status] = (int) ($raw[$status] ?? 0);
        }

        $taskTotal = array_sum($statusCounts);
        $doneTasks = $statusCounts['done'] + $statusCounts['archive'];

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
        ];
    }
}

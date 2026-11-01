<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseOffDay;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Release planning: the filtered index, and the write sequence that keeps a
 * release consistent with its phases, off-days, and assigned members.
 *
 * The rule worth stating once, here, is that a same-team scheduling overlap is
 * a **warning and never a rejection**. Both delivery layers save first and
 * report the conflict afterwards, so a planner — not the tool — decides whether
 * a double-booking is acceptable.
 */
class ReleaseService
{
    /** The attributes a release write accepts directly. */
    private const WRITABLE = [
        'project_id', 'team_id', 'name', 'description', 'year', 'quarter', 'start_date', 'end_date',
    ];

    public function __construct(private readonly OverlapChecker $overlap) {}

    /** Status filter values the index accepts; `active` is every ongoing (not completed) release. */
    public const STATUSES = ['active', 'in_progress', 'upcoming', 'overdue', 'completed'];

    /** Sort keys for filtered(); prefix with `-` for descending. */
    public const SORTS = ['start', 'end', 'name', 'progress'];

    /**
     * The release index query, shared by the Blade list and the API collection.
     *
     * Every key is optional. `status` takes one of STATUSES — the date-based
     * ones (`in_progress`, `upcoming`, `overdue`) mirror Release::state() in
     * SQL. `sort` takes one of SORTS, optionally `-`-prefixed; without it the
     * list keeps its newest-quarter-first order.
     *
     * @param  array{status?: ?string, project_id?: ?int, team_id?: ?int, year?: ?int, quarter?: ?int, search?: ?string, sort?: ?string}  $filters
     * @return Builder<Release>
     */
    public function filtered(array $filters = []): Builder
    {
        $query = $this->scoped($filters)->with(['project', 'team', 'completedBy']);

        $this->applyStatus($query, $filters['status'] ?? null);

        return $this->applySort($query, $filters['sort'] ?? null);
    }

    /**
     * The web index listing: filtered() plus each release's task progress
     * (Release::withTaskProgress) and its members for the avatar stack.
     *
     * @param  array<string, mixed>  $filters  as for filtered()
     * @return Builder<Release>
     */
    public function listing(array $filters = []): Builder
    {
        return $this->filtered($filters)
            ->withTaskProgress()
            ->with(['members' => fn ($q) => $q->select('users.id', 'users.name')->orderBy('users.name')]);
    }

    /**
     * How many releases sit in each Release::state(), under every filter but
     * status — the index's quick-filter strip.
     *
     * @param  array<string, mixed>  $filters  as for filtered()
     * @return array<string, int> keyed by Release::STATES
     */
    public function stateCounts(array $filters = []): array
    {
        $counts = $this->scoped($filters)
            ->get(['id', 'start_date', 'end_date', 'completed_at'])
            ->countBy(fn (Release $release) => $release->state());

        return array_map(fn (string $state) => (int) ($counts[$state] ?? 0), array_combine(
            array_keys(Release::STATES), array_keys(Release::STATES)
        ));
    }

    /**
     * Which of these releases double-book their team, checked against every
     * ongoing release of the same teams — not just the ones on this page — so
     * a filter or page break never hides a clash.
     *
     * @param  Collection<int, Release>  $releases
     * @return array<int, bool> keyed by release id (OverlapChecker::flagConflicts)
     */
    public function conflictFlags(Collection $releases): array
    {
        $teamIds = $releases->reject->isComplete()->pluck('team_id')->unique()->values();

        if ($teamIds->isEmpty()) {
            return [];
        }

        $pool = Release::query()->ongoing()->whereIn('team_id', $teamIds)
            ->get(['id', 'team_id', 'start_date', 'end_date']);

        return array_intersect_key($this->overlap->flagConflicts($pool), $releases->keyBy('id')->all());
    }

    /**
     * Task counts for the release page's KPI strip, over the tasks the viewer
     * may see. Archived tasks never count (Task::scopeCounted); subtasks do,
     * as in Release::withTaskProgress().
     *
     * @return array{total: int, done: int, open: int, overdue: int}
     */
    public function taskStats(Release $release, User $viewer): array
    {
        $base = fn () => $release->tasks()->visibleTo($viewer)->counted();

        $total = $base()->count();
        $done = $base()->where('status', 'done')->count();
        $overdue = $base()->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today()->toDateString())
            ->count();

        return ['total' => $total, 'done' => $done, 'open' => $total - $done, 'overdue' => $overdue];
    }

    /** Every distinct year that has a release, newest first. */
    public function years(): Collection
    {
        return Release::query()->select('year')->distinct()->orderBy('year', 'desc')->pluck('year');
    }

    /**
     * The filters every listing shares — everything but status and sort.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Release>
     */
    private function scoped(array $filters): Builder
    {
        return Release::query()
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['team_id'] ?? null, fn ($q, $id) => $q->where('team_id', $id))
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->where('year', $year))
            ->when($filters['quarter'] ?? null, fn ($q, $quarter) => $q->where('quarter', $quarter))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'));
    }

    /**
     * @param  Builder<Release>  $query
     */
    private function applyStatus(Builder $query, ?string $status): void
    {
        $today = today()->toDateString();

        match ($status) {
            'active' => $query->ongoing(),
            'completed' => $query->completed(),
            'upcoming' => $query->ongoing()->whereDate('start_date', '>', $today),
            'in_progress' => $query->ongoing()
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today),
            'overdue' => $query->ongoing()->whereDate('end_date', '<', $today),
            default => null,
        };
    }

    /**
     * @param  Builder<Release>  $query
     * @return Builder<Release>
     */
    private function applySort(Builder $query, ?string $sort): Builder
    {
        $sort = (string) $sort;
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return match (ltrim($sort, '-')) {
            'start' => $query->orderBy('start_date', $dir)->orderBy('id', $dir),
            'end' => $query->orderBy('end_date', $dir)->orderBy('id', $dir),
            'name' => $query->orderByRaw('lower(name) '.$dir)->orderBy('id'),
            'progress' => $this->orderByProgress($query, $dir)->orderBy('start_date', 'desc'),
            default => $query->orderBy('year', 'desc')->orderBy('quarter', 'desc')->orderBy('start_date', 'desc'),
        };
    }

    /**
     * Order by the share of counted tasks done — the same numbers as
     * Release::withTaskProgress(), written as correlated subqueries so the sort
     * never depends on a select alias. Releases with no tasks sort lowest.
     *
     * @param  Builder<Release>  $query
     * @return Builder<Release>
     */
    private function orderByProgress(Builder $query, string $dir): Builder
    {
        $done = Task::query()->selectRaw('count(*)')
            ->whereColumn('tasks.release_id', 'releases.id')
            ->where('status', 'done');
        $total = Task::query()->selectRaw('count(*)')
            ->whereColumn('tasks.release_id', 'releases.id')
            ->counted();

        return $query->orderByRaw(
            sprintf('coalesce((%s) * 1.0 / nullif((%s), 0), -1) %s', $done->toSql(), $total->toSql(), $dir),
            array_merge($done->getBindings(), $total->getBindings()),
        );
    }

    /**
     * Create a release together with its phases, off-days, and members.
     *
     * @param  array<string, mixed>  $attributes  validated release attributes
     * @param  array<string, array{start?: string, end?: string}>  $phases
     * @param  array<int, array{date?: string, reason?: string}>  $offDays
     * @param  array<int, int|string>  $memberIds
     */
    public function create(array $attributes, array $phases = [], array $offDays = [], array $memberIds = []): Release
    {
        return DB::transaction(function () use ($attributes, $phases, $offDays, $memberIds) {
            $release = Release::create($this->writable($attributes));

            $this->syncPhases($release, $phases);
            $this->syncOffDays($release, $offDays);
            $release->members()->sync($memberIds);

            return $release;
        });
    }

    /**
     * Update a release and reconcile its phases, off-days, and members.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array{start?: string, end?: string}>  $phases
     * @param  array<int, array{date?: string, reason?: string}>  $offDays
     * @param  array<int, int|string>  $memberIds
     */
    public function update(Release $release, array $attributes, array $phases = [], array $offDays = [], array $memberIds = []): Release
    {
        DB::transaction(function () use ($release, $attributes, $phases, $offDays, $memberIds) {
            $release->update($this->writable($attributes));

            $this->syncPhases($release, $phases);
            $this->syncOffDays($release, $offDays);
            $release->members()->sync($memberIds);
        });

        return $release->refresh();
    }

    public function complete(Release $release, User $by, ?string $notes = null): Release
    {
        $release->update([
            'completed_at' => now(),
            'completed_by' => $by->id,
            'completion_notes' => $notes,
        ]);

        return $release;
    }

    public function reopen(Release $release): Release
    {
        $release->update(['completed_at' => null, 'completed_by' => null]);

        return $release;
    }

    /**
     * Other releases this one double-books its team against.
     *
     * @return Collection<int, Release>
     */
    public function conflictsFor(Release $release): Collection
    {
        return $this->overlap->conflictsFor(
            $release->team_id,
            $release->start_date->toDateString(),
            $release->end_date->toDateString(),
            $release->id
        );
    }

    /**
     * The human warning for a double-booked team, or null when the team is free.
     *
     * @param  Collection<int, Release>|null  $conflicts  reuse an already-loaded set
     */
    public function overlapMessage(Release $release, ?Collection $conflicts = null): ?string
    {
        $conflicts ??= $this->conflictsFor($release);

        if ($conflicts->isEmpty()) {
            return null;
        }

        $list = $conflicts->map(fn (Release $c) => sprintf(
            '“%s” (%s – %s)',
            $c->name,
            $c->start_date->format('M j'),
            $c->end_date->format('M j, Y')
        ))->implode('; ');

        return sprintf(
            'Heads up: team %s is already booked during this window by %s. The release was saved anyway.',
            $release->team->name,
            $list
        );
    }

    /**
     * The owning team's active members plus any current assignee who has since
     * left, so a task never renders a blank assignee.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(Release $release): Collection
    {
        $current = $release->relationLoaded('rootTasks')
            ? collect($release->rootTasks)->map->assignee->filter()
                ->concat(collect($release->rootTasks)->flatMap(fn ($t) => $t->subtasks->map->assignee)->filter())
            : collect();

        return $release->team->members()->active()->orderBy('name')->get()
            ->concat($current)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Recreate the four canonical phases in order from the submitted windows.
     *
     * They are rewritten as a set rather than patched individually, which is
     * what guarantees a release always has exactly four, in order, inside its
     * own window.
     *
     * @param  array<string, array{start?: string, end?: string}>  $phases
     */
    private function syncPhases(Release $release, array $phases): void
    {
        $release->phases()->delete();

        $position = 0;
        foreach (array_keys(Release::PHASES) as $key) {
            $release->phases()->create([
                'phase' => $key,
                'position' => $position++,
                'start_date' => $phases[$key]['start'] ?? $release->start_date,
                'end_date' => $phases[$key]['end'] ?? $release->end_date,
            ]);
        }
    }

    /**
     * Reconcile off-days against those submitted, keyed by date so unchanged
     * rows are left alone — which keeps the activity log quiet on every save.
     *
     * @param  array<int, array{date?: string, reason?: string}>  $offDays
     */
    private function syncOffDays(Release $release, array $offDays): void
    {
        $submitted = collect($offDays)
            ->filter(fn ($o) => filled($o['date'] ?? null))
            ->keyBy(fn ($o) => Carbon::parse($o['date'])->toDateString());

        $existing = $release->offDays()->get()
            ->keyBy(fn (ReleaseOffDay $o) => $o->date->toDateString());

        foreach ($existing as $date => $offDay) {
            if (! $submitted->has($date)) {
                $offDay->delete();
            }
        }

        foreach ($submitted as $date => $data) {
            $reason = $data['reason'] ?? null;

            if (! $existing->has($date)) {
                $release->offDays()->create(['date' => $date, 'reason' => $reason]);

                continue;
            }

            $offDay = $existing[$date];
            if ($offDay->reason !== $reason) {
                $offDay->update(['reason' => $reason]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function writable(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(self::WRITABLE));
    }
}

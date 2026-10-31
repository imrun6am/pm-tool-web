<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseOffDay;
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

    /**
     * The release index query, shared by the Blade list and the API collection.
     *
     * @param  array{status?: ?string, project_id?: ?int, team_id?: ?int, year?: ?int, search?: ?string}  $filters
     * @return Builder<Release>
     */
    public function filtered(array $filters = []): Builder
    {
        $status = $filters['status'] ?? null;

        return Release::query()
            ->with(['project', 'team', 'completedBy'])
            ->when($status === 'active', fn ($q) => $q->ongoing())
            ->when($status === 'completed', fn ($q) => $q->completed())
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['team_id'] ?? null, fn ($q, $id) => $q->where('team_id', $id))
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->where('year', $year))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('year', 'desc')
            ->orderBy('quarter', 'desc')
            ->orderBy('start_date', 'desc');
    }

    /** Every distinct year that has a release, newest first. */
    public function years(): Collection
    {
        return Release::query()->select('year')->distinct()->orderBy('year', 'desc')->pluck('year');
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

<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Roll-ups over the app-wide audit feed.
 *
 * Every method takes the same optional "focus" — a causer to scope to — so the
 * KPI cards, the charts, and the feed itself all describe the same slice. The
 * event filter deliberately narrows *only* the feed: applying it to the
 * breakdown charts as well would leave them showing a single bar.
 *
 * Performance scores never appear here. `PerformanceScore` does not use the
 * RecordsActivity trait precisely because this feed is readable by every
 * full-access user and ratings are not.
 */
class ActivityInsights
{
    /** How many days of history the trend chart covers. */
    public const TREND_DAYS = 14;

    /** How many contributors the leaderboard shows. */
    public const TOP_CONTRIBUTORS = 5;

    /** How many subject types the breakdown shows. */
    public const TOP_SUBJECT_TYPES = 6;

    /**
     * The paginated feed, narrowed by every supplied filter.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Activity>
     */
    public function feed(array $filters = []): Builder
    {
        return $this->scoped($filters['causer_id'] ?? null)
            ->with('causer')
            ->when($filters['release_id'] ?? null, fn ($q, $id) => $q->where('release_id', $id))
            ->when(
                in_array($filters['event'] ?? null, ['created', 'updated', 'deleted'], true),
                fn ($q) => $q->where('event', $filters['event'])
            )
            ->when($filters['subject_type'] ?? null, fn ($q, $type) => $q->where(
                'subject_type',
                'App\\Models\\'.Str::studly($type)
            ))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest();
    }

    /**
     * Headline counts for the focused slice.
     *
     * @return array<string, int>
     */
    public function totals(?int $causerId = null): array
    {
        $eventCounts = $this->scoped($causerId)
            ->select('event', DB::raw('count(*) as aggregate'))
            ->groupBy('event')
            ->pluck('aggregate', 'event');

        $created = (int) ($eventCounts['created'] ?? 0);
        $updated = (int) ($eventCounts['updated'] ?? 0);
        $deleted = (int) ($eventCounts['deleted'] ?? 0);

        return [
            'total' => $created + $updated + $deleted,
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'today' => (int) $this->scoped($causerId)->whereDate('created_at', Carbon::today())->count(),
            'week' => (int) $this->scoped($causerId)
                ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
                ->count(),
            'contributors' => (int) $this->scoped($causerId)
                ->whereNotNull('causer_id')
                ->distinct()
                ->count('causer_id'),
        ];
    }

    /**
     * Daily volume over the trailing fortnight, with quiet days filled as zero
     * so a client charting it gets a continuous axis.
     *
     * @return list<array{date: Carbon, count: int}>
     */
    public function trend(?int $causerId = null): array
    {
        $span = self::TREND_DAYS - 1;

        $daily = $this->scoped($causerId)
            ->where('created_at', '>=', Carbon::today()->subDays($span))
            ->select(DB::raw('date(created_at) as d'), DB::raw('count(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd');

        $trend = [];
        for ($i = $span; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $trend[] = [
                'date' => $day,
                'count' => (int) ($daily[$day->format('Y-m-d')] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * The busiest people in the focused slice, with their causer loaded.
     *
     * @return Collection<int, Activity>
     */
    public function topContributors(?int $causerId = null): Collection
    {
        return $this->scoped($causerId)
            ->whereNotNull('causer_id')
            ->select('causer_id', DB::raw('count(*) as c'))
            ->groupBy('causer_id')
            ->orderByDesc('c')
            ->limit(self::TOP_CONTRIBUTORS)
            ->with('causer')
            ->get();
    }

    /**
     * Which kinds of record saw the most activity. The model class is reduced to
     * a short slug and a human label so no caller has to know the namespace.
     *
     * @return Collection<int, array{type: string, label: string, count: int}>
     */
    public function bySubjectType(?int $causerId = null): Collection
    {
        return $this->scoped($causerId)
            ->whereNotNull('subject_type')
            ->select('subject_type', DB::raw('count(*) as c'))
            ->groupBy('subject_type')
            ->orderByDesc('c')
            ->limit(self::TOP_SUBJECT_TYPES)
            ->get()
            ->map(fn ($row) => [
                'type' => Str::snake(class_basename($row->subject_type)),
                'label' => Str::headline(class_basename($row->subject_type)),
                'count' => (int) $row->c,
            ]);
    }

    /**
     * A fresh query scoped to the focused causer.
     *
     * Returned as a new builder each time rather than shared, so the callers
     * above cannot accidentally stack each other's constraints.
     *
     * @return Builder<Activity>
     */
    private function scoped(?int $causerId): Builder
    {
        return Activity::query()->when($causerId, fn ($q) => $q->where('causer_id', $causerId));
    }
}

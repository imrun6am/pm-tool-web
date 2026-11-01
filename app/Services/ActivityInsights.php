<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Metric;
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
 * analysis() is the deeper page: one period window (7/30/90 days) against
 * the one before it, scoped by person, team, project or release. Its charts
 * ignore the *slice* filters (event, record type, a single day) — those are
 * the drill-down targets that narrow the feed below them.
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

    /** Analysis windows, in days. */
    public const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    public const DEFAULT_DAYS = 30;

    /** Rows shown in each ranked breakdown on the analysis page. */
    public const TOP_N = 8;

    /** Extra history read before the window to time the first moves inside it. */
    public const FLOW_LOOKBACK_DAYS = 60;

    /** The workflow statuses whose dwell time is measured. */
    public const FLOW_STATUSES = ['todo', 'in_progress', 'in_review', 'recheck'];

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
            ->when($filters['team_id'] ?? null, fn ($q, $id) => $q->whereIn('release_id', Release::select('id')->where('team_id', $id)))
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->whereIn('release_id', Release::select('id')->where('project_id', $id)))
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
     * Normalize raw input (query string or API params) into analysis filters.
     * Unknown periods fall back to the default; ids become ints or null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function filters(array $input): array
    {
        $id = fn (string $key) => ! empty($input[$key]) ? (int) $input[$key] : null;
        $days = (int) ($input['days'] ?? self::DEFAULT_DAYS);
        $date = fn (string $key) => ! empty($input[$key]) ? Carbon::parse($input[$key])->toDateString() : null;

        return [
            'days' => array_key_exists($days, self::PERIODS) ? $days : self::DEFAULT_DAYS,
            'causer_id' => $id('causer_id'),
            'team_id' => $id('team_id'),
            'project_id' => $id('project_id'),
            'release_id' => $id('release_id'),
            'event' => in_array($input['event'] ?? null, ['created', 'updated', 'deleted'], true) ? $input['event'] : null,
            'subject_type' => ! empty($input['subject_type']) ? Str::snake((string) $input['subject_type']) : null,
            'from' => $date('from'),
            'to' => $date('to'),
        ];
    }

    /**
     * The analysis window and the equally long one before it.
     *
     * @param  array<string, mixed>  $filters
     * @return array{days: int, label: string, start: Carbon, end: Carbon, previousStart: Carbon, previousEnd: Carbon}
     */
    public function window(array $filters): array
    {
        $days = $filters['days'];
        $start = Carbon::today()->subDays($days - 1);

        return [
            'days' => $days,
            'label' => self::PERIODS[$days],
            'start' => $start,
            'end' => Carbon::now(),
            'previousStart' => $start->copy()->subDays($days),
            'previousEnd' => $start->copy()->subSecond(),
        ];
    }

    /**
     * The feed for the analysis page: every filter applies, and the period
     * window bounds it unless a drill-down supplied its own dates.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Activity>
     */
    public function windowFeed(array $filters): Builder
    {
        $window = $this->window($filters);

        return $this->feed([
            ...$filters,
            'from' => $filters['from'] ?? $window['start']->toDateString(),
        ]);
    }

    /**
     * Everything the activity analysis page charts, for one scope and window.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function analysis(array $filters): array
    {
        $window = $this->window($filters);
        $inWindow = fn (): Builder => $this->scopedBy($filters)
            ->where('activities.created_at', '>=', $window['start'])
            ->where('activities.created_at', '<=', $window['end']);
        $inPrevious = fn (): Builder => $this->scopedBy($filters)
            ->whereBetween('activities.created_at', [$window['previousStart'], $window['previousEnd']]);

        $counts = fn (Builder $q) => $q->selectRaw(
            'count(*) as total, count(distinct causer_id) as people,
             sum(case when event = ? then 1 else 0 end) as deleted',
            ['deleted']
        )->first();
        $now = $counts($inWindow());
        $before = $counts($inPrevious());

        $byEvent = $inWindow()->select('event', DB::raw('count(*) as c'))->groupBy('event')->pluck('c', 'event');

        return [
            'window' => $window,
            'kpis' => [
                'events' => Metric::compare((int) $now->total, (int) $before->total),
                'people' => Metric::compare((int) $now->people, (int) $before->people),
                'deletions' => Metric::compare((int) $now->deleted, (int) $before->deleted),
                'perDay' => round((int) $now->total / $window['days'], 1),
            ],
            'volume' => $this->volume($inWindow, $window),
            'byEvent' => collect(['created', 'updated', 'deleted'])
                ->mapWithKeys(fn ($e) => [$e => (int) ($byEvent[$e] ?? 0)])->all(),
            'byType' => $inWindow()
                ->whereNotNull('subject_type')
                ->select('subject_type', DB::raw('count(*) as c'))
                ->groupBy('subject_type')->orderByDesc('c')->limit(self::TOP_N)->get()
                ->map(fn ($row) => [
                    'type' => Str::snake(class_basename($row->subject_type)),
                    'label' => Str::headline(class_basename($row->subject_type)),
                    'count' => (int) $row->c,
                ])->all(),
            'byRelease' => $this->byRelease($inWindow),
            'byTeam' => $this->byTeam($inWindow),
            'people' => $this->people($inWindow),
            'rhythm' => $this->rhythm($inWindow),
            'flow' => $this->flow($filters, $window),
            'deletions' => $inWindow()->with('causer')->where('event', 'deleted')
                ->latest('activities.created_at')->latest('activities.id')->limit(self::TOP_N)->get(),
        ];
    }

    /**
     * Event counts per day (7/30-day windows) or per week (90 days), quiet
     * buckets included so the axis is continuous.
     *
     * @param  \Closure(): Builder  $inWindow
     * @param  array<string, mixed>  $window
     * @return list<array{start: Carbon, end: Carbon, label: string, count: int, current: bool}>
     */
    private function volume(\Closure $inWindow, array $window): array
    {
        $daily = $inWindow()
            ->select(DB::raw('date(activities.created_at) as d'), DB::raw('count(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd');

        $weekly = $window['days'] > 30;
        $buckets = [];
        for ($cursor = $window['start']->copy(); $cursor->lte($window['end']); $cursor->addDays($weekly ? 7 : 1)) {
            $end = $weekly ? $cursor->copy()->addDays(6)->min(Carbon::today()) : $cursor->copy();
            $count = 0;
            for ($d = $cursor->copy(); $d->lte($end); $d->addDay()) {
                $count += (int) ($daily[$d->toDateString()] ?? 0);
            }
            $buckets[] = [
                'start' => $cursor->copy(),
                'end' => $end,
                'label' => $cursor->format('M j'),
                'count' => $count,
                'current' => $end->isToday(),
            ];
        }

        return $buckets;
    }

    /**
     * @param  \Closure(): Builder  $inWindow
     * @return list<array{id: int, name: string, color: string, count: int}>
     */
    private function byRelease(\Closure $inWindow): array
    {
        $rows = $inWindow()->whereNotNull('release_id')
            ->select('release_id', DB::raw('count(*) as c'))
            ->groupBy('release_id')->orderByDesc('c')->limit(self::TOP_N)->get();

        // Plain column, not a FK: a deleted release's history keeps its id.
        $releases = Release::with('team:id,color')->whereIn('id', $rows->pluck('release_id'))->get(['id', 'name', 'team_id'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'id' => (int) $row->release_id,
            'name' => $releases[$row->release_id]->name ?? 'Deleted release',
            'color' => $releases[$row->release_id]?->team?->color ?? '#94a3b8',
            'count' => (int) $row->c,
        ])->all();
    }

    /**
     * Activity per owning team (through the release it belongs to).
     *
     * @param  \Closure(): Builder  $inWindow
     * @return list<array{id: ?int, name: string, color: string, count: int}>
     */
    private function byTeam(\Closure $inWindow): array
    {
        $rows = $inWindow()
            ->leftJoin('releases', 'releases.id', '=', 'activities.release_id')
            ->select('releases.team_id', DB::raw('count(*) as c'))
            ->groupBy('releases.team_id')->orderByDesc('c')->get();

        $teams = Team::whereIn('id', $rows->pluck('team_id')->filter())->get(['id', 'name', 'color'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'id' => $row->team_id ? (int) $row->team_id : null,
            'name' => $row->team_id ? ($teams[$row->team_id]->name ?? 'Deleted team') : 'Not release-linked',
            'color' => $row->team_id ? ($teams[$row->team_id]->color ?? '#94a3b8') : '#cbd5e1',
            'count' => (int) $row->c,
        ])->all();
    }

    /**
     * The most active people, with their event mix.
     *
     * @param  \Closure(): Builder  $inWindow
     * @return list<array{user: User, total: int, created: int, updated: int, deleted: int}>
     */
    private function people(\Closure $inWindow): array
    {
        $rows = $inWindow()->whereNotNull('causer_id')
            ->selectRaw(
                'causer_id, count(*) as total,
                 sum(case when event = ? then 1 else 0 end) as created_count,
                 sum(case when event = ? then 1 else 0 end) as updated_count,
                 sum(case when event = ? then 1 else 0 end) as deleted_count',
                ['created', 'updated', 'deleted']
            )
            ->groupBy('causer_id')->orderByDesc('total')->orderBy('causer_id')->limit(self::TOP_N)->get();

        $users = User::withTrashed()->whereIn('id', $rows->pluck('causer_id'))->get()->keyBy('id');

        return $rows->filter(fn ($row) => $users->has($row->causer_id))->map(fn ($row) => [
            'user' => $users[$row->causer_id],
            'total' => (int) $row->total,
            'created' => (int) $row->created_count,
            'updated' => (int) $row->updated_count,
            'deleted' => (int) $row->deleted_count,
        ])->values()->all();
    }

    /**
     * When work happens: counts by weekday (Monday first) and hour, in app time.
     *
     * @param  \Closure(): Builder  $inWindow
     * @return array{grid: list<array{day: string, hours: list<int>}>, max: int, peak: ?array{day: string, hour: int, count: int}}
     */
    private function rhythm(\Closure $inWindow): array
    {
        [$dow, $hour] = match (DB::connection()->getDriverName()) {
            'sqlite' => ["cast(strftime('%w', activities.created_at) as integer)", "cast(strftime('%H', activities.created_at) as integer)"],
            'pgsql' => ['extract(dow from activities.created_at)', 'extract(hour from activities.created_at)'],
            default => ['(dayofweek(activities.created_at) - 1)', 'hour(activities.created_at)'],
        };

        $cells = $inWindow()
            ->selectRaw("{$dow} as dow, {$hour} as hr, count(*) as c")
            ->groupByRaw("{$dow}, {$hour}")
            ->get();

        $counts = [];
        foreach ($cells as $cell) {
            $counts[(int) $cell->dow][(int) $cell->hr] = (int) $cell->c;
        }

        $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
        $grid = [];
        $peak = null;
        foreach ($days as $index => $label) {
            $hours = [];
            for ($h = 0; $h < 24; $h++) {
                $c = $counts[$index][$h] ?? 0;
                $hours[] = $c;
                if ($c > 0 && ($peak === null || $c > $peak['count'])) {
                    $peak = ['day' => $label, 'hour' => $h, 'count' => $c];
                }
            }
            $grid[] = ['day' => $label, 'hours' => $hours];
        }

        return ['grid' => $grid, 'max' => $peak['count'] ?? 0, 'peak' => $peak];
    }

    /**
     * How tasks moved through the workflow in the window: the busiest
     * status-to-status moves, named milestones (started, sent to review,
     * rework, done, reopened), and how long work sat in each status before
     * leaving it. Dwell times need the moment a task *entered* a status, so
     * history is read from a little before the window.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $window
     * @return array<string, mixed>
     */
    private function flow(array $filters, array $window): array
    {
        // A task's `created` row may omit status (the column default never sat
        // on the model), so creations are read whatever they carry.
        $events = $this->scopedBy($filters)
            ->where('subject_type', (new Task)->getMorphClass())
            ->where(fn ($q) => $q->where('event', 'created')
                ->orWhere(fn ($q) => $q->where('event', 'updated')->whereNotNull('properties->attributes->status')))
            ->where('activities.created_at', '>=', $window['start']->copy()->subDays(self::FLOW_LOOKBACK_DAYS))
            ->where('activities.created_at', '<=', $window['end'])
            ->orderBy('activities.created_at')->orderBy('activities.id')
            ->get(['activities.id', 'subject_id', 'event', 'properties', 'activities.created_at']);

        $labels = Task::STATUSES;
        $pairs = [];
        $milestones = ['started' => 0, 'review' => 0, 'rework' => 0, 'done' => 0, 'reopened' => 0];
        $dwell = array_fill_keys(self::FLOW_STATUSES, []);
        $entered = [];

        $last = [];

        foreach ($events as $a) {
            $task = $a->subject_id;
            // Missing values fall back to what is known: a creation without a
            // status is the column default, and a move whose diff lost its old
            // value left the status the task last entered.
            $new = $a->properties['attributes']['status'] ?? ($a->event === 'created' ? 'todo' : null);
            $old = $a->event === 'created' ? null : ($a->properties['old']['status'] ?? $last[$task] ?? 'todo');

            if ($a->created_at->gte($window['start']) && $old !== null) {
                $key = $old.'>'.$new;
                $pairs[$key] = ($pairs[$key] ?? 0) + 1;

                match (true) {
                    $new === 'in_progress' && $old === 'todo' => $milestones['started']++,
                    $new === 'in_review' => $milestones['review']++,
                    $new === 'recheck' => $milestones['rework']++,
                    $new === 'done' && ! in_array($old, Task::DONE_STATUSES, true) => $milestones['done']++,
                    in_array($old, Task::DONE_STATUSES, true) && ! in_array($new, Task::DONE_STATUSES, true) => $milestones['reopened']++,
                    default => null,
                };

                if (isset($dwell[$old]) && isset($entered[$task][$old])) {
                    $dwell[$old][] = $entered[$task][$old]->diffInSeconds($a->created_at) / 3600;
                }
            }

            if ($new !== null) {
                $entered[$task][$new] = $a->created_at;
                $last[$task] = $new;
            }
        }

        arsort($pairs);

        return [
            'moves' => array_sum($pairs),
            'milestones' => $milestones,
            // Of the moves into review/done, how often work bounced back to Recheck.
            'reworkRate' => Metric::pct($milestones['rework'], $milestones['done'] + $milestones['rework']),
            'transitions' => collect($pairs)->take(self::TOP_N)->map(function (int $count, string $key) use ($labels) {
                [$from, $to] = explode('>', $key);

                return [
                    'from' => $from,
                    'to' => $to,
                    'label' => ($labels[$from] ?? $from).' → '.($labels[$to] ?? $to),
                    'count' => $count,
                ];
            })->values()->all(),
            'dwell' => collect($dwell)->map(fn (array $hours, string $status) => [
                'status' => $status,
                'label' => $labels[$status],
                'avgHours' => $hours ? round(array_sum($hours) / count($hours), 1) : null,
                'samples' => count($hours),
            ])->values()->all(),
        ];
    }

    /**
     * Activities narrowed by the analysis *scope* — person, team, project,
     * release — but not by the slice filters the feed alone applies.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Activity>
     */
    private function scopedBy(array $filters): Builder
    {
        return Activity::query()
            ->when($filters['causer_id'] ?? null, fn ($q, $id) => $q->where('causer_id', $id))
            ->when($filters['release_id'] ?? null, fn ($q, $id) => $q->where('activities.release_id', $id))
            ->when($filters['team_id'] ?? null, fn ($q, $id) => $q->whereIn('activities.release_id', Release::select('id')->where('team_id', $id)))
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->whereIn('activities.release_id', Release::select('id')->where('project_id', $id)));
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

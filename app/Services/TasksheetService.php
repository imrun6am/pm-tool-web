<?php

namespace App\Services;

use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The team's daily tasksheet.
 *
 * Two rules drive most of this class:
 *
 * 1. **History does not rewrite itself.** The rows for a past date are the
 *    people whose membership *covered that date* — including anyone since
 *    removed, deactivated, or deleted. If departures pruned the roster
 *    retroactively, last month's sheet would silently change.
 * 2. **`feedback` belongs to leads alone.** A member's save never reaches it,
 *    so it can be neither blanked nor forged from a member's client.
 *
 * Note the `whereDate` bounds in trend(): the model's `date` cast stores a
 * midnight *timestamp*, so comparing it against plain 'Y-m-d' bounds sorts
 * '2026-07-26 00:00:00' after '2026-07-26' and silently drops the most recent
 * day — the very day the chart is centred on.
 */
class TasksheetService
{
    /** How many days of output the trend chart covers, including the viewed day. */
    public const TREND_DAYS = 14;

    /** Active teams, the viewer's own first. */
    public function teamsFor(User $viewer): Collection
    {
        $myTeamIds = $viewer->teams()->pluck('teams.id');

        return Team::active()->orderBy('name')->get()
            ->sortBy(fn (Team $t) => [$myTeamIds->contains($t->id) ? 0 : 1, $t->name])
            ->values();
    }

    /**
     * Resolve the selected team within the set the viewer may pick from,
     * falling back to the first.
     *
     * @param  Collection<int, Team>  $teams
     */
    public function resolveTeam(Collection $teams, ?int $teamId): ?Team
    {
        return ($teamId ? $teams->firstWhere('id', $teamId) : null) ?? $teams->first();
    }

    /**
     * That day's saved rows for a team, keyed by user.
     *
     * @return Collection<int, TasksheetEntry>
     */
    public function entriesFor(Team $team, Carbon $day): Collection
    {
        return TasksheetEntry::with('member')
            ->where('team_id', $team->id)
            ->whereDate('date', $day->toDateString())
            ->get()
            ->keyBy('user_id');
    }

    /**
     * The people who belong on a given day's sheet: developers and QA whose
     * membership covered that date, plus anyone with a saved entry that day.
     *
     * @param  Collection<int, TasksheetEntry>  $entries
     * @return Collection<int, User>
     */
    public function rowUsersFor(Team $team, Carbon $day, Collection $entries): Collection
    {
        $dayStr = $day->toDateString();

        $members = $team->memberRecords()
            ->withTrashed()
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
            ->get()
            ->filter(fn (User $u) => $this->coveredDate($u, $dayStr));

        return $members
            ->concat($entries->map(fn (TasksheetEntry $e) => $e->member)->filter())
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Total work points booked per day over the trailing fortnight, ending on
     * the viewed day, with quiet days filled as zero so the axis is continuous.
     *
     * @return list<array<string, mixed>>
     */
    public function trend(Team $team, Carbon $day): array
    {
        $span = self::TREND_DAYS - 1;
        $windowStart = $day->copy()->subDays($span);

        $byDay = TasksheetEntry::where('team_id', $team->id)
            ->whereDate('date', '>=', $windowStart->toDateString())
            ->whereDate('date', '<=', $day->toDateString())
            ->selectRaw('date, COALESCE(SUM(work_points), 0) as wp')
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($r) => [Carbon::parse($r->date)->toDateString() => (int) $r->wp]);

        $trend = [];
        for ($i = $span; $i >= 0; $i--) {
            $d = $day->copy()->subDays($i);

            $trend[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('j'),
                'dow' => $d->format('D'),
                'wp' => $byDay[$d->toDateString()] ?? 0,
                'current' => $d->isSameDay($day),
            ];
        }

        return $trend;
    }

    /**
     * One member's history, filterable by team and date range.
     *
     * @return Builder<TasksheetEntry>
     */
    public function history(User $member, ?int $teamId = null, ?string $from = null, ?string $to = null): Builder
    {
        return TasksheetEntry::with(['team', 'member'])
            ->where('user_id', $member->id)
            ->when($teamId, fn ($q, $id) => $q->where('team_id', $id))
            ->when($from, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($to, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->orderByDesc('date')
            ->orderByDesc('id');
    }

    /**
     * Normalize the filter inputs both surfaces accept.
     *
     * One parser for two very different code paths — the daily sheet filters a
     * collection, the history filters a query — so `attendance=unmarked` cannot
     * come to mean one thing on one page and something else on the other.
     *
     * @param  array<string, mixed>  $input
     * @return array{attendance: ?string, fill: ?string, leave: ?string, member: ?int}
     */
    public function parseFilters(array $input): array
    {
        $oneOf = fn (?string $value, array $allowed) => in_array($value, $allowed, true) ? $value : null;

        return [
            'attendance' => $oneOf($input['attendance'] ?? null, ['attended', 'missed', 'unmarked']),
            'fill' => $oneOf($input['fill'] ?? null, ['complete', 'partial', 'empty']),
            'leave' => $oneOf($input['leave'] ?? null, ['working', 'any', ...array_keys(TasksheetEntry::LEAVE_TYPES)]),
            'member' => filled($input['member'] ?? null) ? (int) $input['member'] : null,
        ];
    }

    /**
     * Whether a row matches the filters. `$entry` is null for a member who has
     * no row that day — a state a SQL predicate cannot express, which is why
     * the daily sheet filters in PHP (design decision 5). The *empty* and
     * *unmarked* filters exist precisely to find those people.
     *
     * @param  array{attendance: ?string, fill: ?string, leave: ?string, member: ?int}  $filters
     */
    public function rowMatches(?TasksheetEntry $entry, int $userId, array $filters): bool
    {
        if ($filters['member'] && $filters['member'] !== $userId) {
            return false;
        }

        return $this->matchesAttendance($entry, $filters['attendance'])
            && $this->matchesFill($entry, $filters['fill'])
            && $this->matchesLeave($entry, $filters['leave']);
    }

    /**
     * Apply the filters to a history query. Mirrors rowMatches() — except that
     * a query only ever sees rows that exist, so "empty" here means a saved row
     * with no task content rather than a missing one.
     *
     * @param  Builder<TasksheetEntry>  $query
     * @param  array{attendance: ?string, fill: ?string, leave: ?string, member: ?int}  $filters
     * @return Builder<TasksheetEntry>
     */
    public function applyHistoryFilters(Builder $query, array $filters): Builder
    {
        $fields = TasksheetEntry::TASK_FIELDS;

        return $query
            ->when($filters['attendance'] === 'attended', fn ($q) => $q->where('standup_attended', true))
            ->when($filters['attendance'] === 'missed', fn ($q) => $q->where('standup_attended', false))
            ->when($filters['attendance'] === 'unmarked', fn ($q) => $q->whereNull('standup_attended'))
            ->when($filters['leave'] === 'working', fn ($q) => $q->whereNull('leave_type'))
            ->when($filters['leave'] === 'any', fn ($q) => $q->whereNotNull('leave_type'))
            ->when(
                $filters['leave'] && ! in_array($filters['leave'], ['working', 'any'], true),
                fn ($q) => $q->where('leave_type', $filters['leave'])
            )
            // Fill status is derived from how many task fields carry a value,
            // matching the model's isFullyFilled()/isPartiallyFilled() rules.
            ->when($filters['fill'] === 'complete', fn ($q) => $this->whereNotFullDayLeave($q)
                ->where(function ($w) use ($fields) {
                    foreach ($fields as $f) {
                        $w->whereNotNull($f);
                    }
                }))
            ->when($filters['fill'] === 'empty', fn ($q) => $q
                ->where(function ($w) use ($fields) {
                    foreach ($fields as $f) {
                        $w->whereNull($f);
                    }
                }))
            ->when($filters['fill'] === 'partial', fn ($q) => $this->whereNotFullDayLeave($q)
                // At least one field filled, and at least one still empty.
                ->where(function ($w) use ($fields) {
                    foreach ($fields as $f) {
                        $w->orWhereNotNull($f);
                    }
                })
                ->where(function ($w) use ($fields) {
                    foreach ($fields as $f) {
                        $w->orWhereNull($f);
                    }
                }));
    }

    /**
     * Rows for a PDF report. One bounded query serves all three shapes: a team
     * over a span, one member over a span, and a single day (a span whose ends
     * match). Ordered by day then member so the document reads chronologically.
     *
     * @param  array{attendance: ?string, fill: ?string, leave: ?string, member: ?int}  $filters
     * @return Collection<int, TasksheetEntry>
     */
    public function reportRows(?int $teamId, ?int $memberId, string $from, string $to, array $filters = []): Collection
    {
        $query = TasksheetEntry::with(['member', 'team'])
            ->when($teamId, fn ($q, $id) => $q->where('team_id', $id))
            ->when($memberId, fn ($q, $id) => $q->where('user_id', $id))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->orderBy('user_id');

        return $this->applyHistoryFilters($query, $filters + [
            'attendance' => null, 'fill' => null, 'leave' => null, 'member' => null,
        ])->get();
    }

    /**
     * The report's span. `date` names a single day; otherwise `from`/`to` bound
     * it, with a reversed span swapped rather than rejected — the same courtesy
     * the per-user history already extends.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: string}
     */
    public function reportRange(array $input): array
    {
        if (filled($input['date'] ?? null)) {
            $day = Carbon::parse($input['date'])->toDateString();

            return [$day, $day];
        }

        $from = filled($input['from'] ?? null) ? Carbon::parse($input['from'])->toDateString() : today()->toDateString();
        $to = filled($input['to'] ?? null) ? Carbon::parse($input['to'])->toDateString() : $from;

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    /**
     * Everything the report template needs. Assembled here rather than in a
     * controller so the web download and the API download cannot diverge.
     *
     * `includeFeedback` is derived from the **requesting** user, never from
     * whose rows these are: feedback is the lead's private note *about* a
     * member, so a member exporting their own history must not receive it.
     *
     * @param  array{attendance: ?string, fill: ?string, leave: ?string, member: ?int}  $filters
     * @return array<string, mixed>
     */
    public function reportData(?int $teamId, ?int $memberId, string $from, string $to, array $filters, User $viewer): array
    {
        return [
            'rows' => $this->reportRows($teamId, $memberId, $from, $to, $filters),
            'team' => $teamId ? Team::find($teamId) : null,
            'member' => $memberId ? User::withTrashed()->find($memberId) : null,
            'from' => $from,
            'to' => $to,
            'includeFeedback' => $viewer->isLead(),
            'generatedBy' => $viewer,
        ];
    }

    /** @param array<string, mixed> $data as returned by reportData() */
    public function reportFilename(array $data): string
    {
        return collect(['tasksheet', $data['member']?->name, $data['team']?->name, $data['from'], $data['to']])
            ->filter()
            ->map(fn ($part) => Str::slug((string) $part))
            ->implode('-').'.pdf';
    }

    /** @param array{attendance: ?string, fill: ?string, leave: ?string, member: ?int} $filters */
    public function hasFilters(array $filters): bool
    {
        return collect($filters)->filter()->isNotEmpty();
    }

    /**
     * "Not a full-day leave", written so a NULL `leave_type` passes. A bare
     * `whereNotIn` would drop every normal working row, because SQL evaluates
     * `NULL NOT IN (…)` as NULL rather than true.
     *
     * @param  Builder<TasksheetEntry>  $query
     * @return Builder<TasksheetEntry>
     */
    private function whereNotFullDayLeave(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereNull('leave_type')
            ->orWhereNotIn('leave_type', TasksheetEntry::FULL_DAY_LEAVE_TYPES));
    }

    private function matchesAttendance(?TasksheetEntry $entry, ?string $filter): bool
    {
        return match ($filter) {
            'attended' => (bool) $entry?->attendedStandup(),
            'missed' => (bool) $entry?->missedStandup(),
            // A member with no row has nothing marked, so they belong here.
            'unmarked' => $entry === null || $entry->isStandupUnmarked(),
            default => true,
        };
    }

    private function matchesFill(?TasksheetEntry $entry, ?string $filter): bool
    {
        return match ($filter) {
            'complete' => (bool) $entry?->isFullyFilled(),
            'partial' => (bool) $entry?->isPartiallyFilled(),
            'empty' => $entry === null || $entry->filledFieldCount() === 0,
            default => true,
        };
    }

    private function matchesLeave(?TasksheetEntry $entry, ?string $filter): bool
    {
        return match (true) {
            $filter === null => true,
            $filter === 'working' => $entry === null || ! $entry->isOnLeave(),
            $filter === 'any' => (bool) $entry?->isOnLeave(),
            default => $entry?->leave_type === $filter,
        };
    }

    /** The teams a member has ever booked time against. */
    public function teamsWithHistory(User $member): Collection
    {
        return Team::whereIn('id', TasksheetEntry::where('user_id', $member->id)->select('team_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Find or build the row for a (team, member, date) so the caller can
     * authorize it before the write.
     *
     * Matched with `whereDate` rather than `firstOrNew`: the date cast stores a
     * midnight timestamp, so an equality match on 'Y-m-d' misses the row on
     * some drivers and would insert a duplicate instead of updating.
     *
     * @param  array{team_id: int|string, user_id: int|string, date: string}  $data
     */
    public function resolveEntry(array $data): TasksheetEntry
    {
        $date = Carbon::parse($data['date'])->toDateString();

        return TasksheetEntry::where('team_id', $data['team_id'])
            ->where('user_id', $data['user_id'])
            ->whereDate('date', $date)
            ->first() ?? new TasksheetEntry([
                'team_id' => $data['team_id'],
                'user_id' => $data['user_id'],
                'date' => $date,
            ]);
    }

    /**
     * Save a row.
     *
     * A full-day leave (casual/sick) clears the task fields — an absent member
     * has no task content. Half-day leave keeps them, since the member still
     * works part of the day. `feedback` is applied only for a lead, and only
     * when the key was actually submitted, so an omitted field is left alone
     * rather than nulled.
     *
     * @param  array<string, mixed>  $data  validated request data
     */
    public function save(TasksheetEntry $entry, array $data, User $actor, bool $feedbackSubmitted = false): TasksheetEntry
    {
        $fields = collect($data)->only([...TasksheetEntry::TASK_FIELDS, 'leave_type'])->all();

        if (in_array($fields['leave_type'] ?? null, TasksheetEntry::FULL_DAY_LEAVE_TYPES, true)) {
            $fields = ['leave_type' => $fields['leave_type']]
                + array_fill_keys(TasksheetEntry::TASK_FIELDS, null);
        }

        $entry->fill($fields);

        // A full day off clears the standup mark along with the task fields:
        // someone legitimately absent is not a standup no-show, and leaving a
        // stale `false` there would read as one.
        if ($entry->isFullDayLeave()) {
            $entry->standup_attended = null;
        }

        if ($actor->isLead() && $feedbackSubmitted) {
            $entry->feedback = $data['feedback'] ?? null;
        }

        $entry->save();

        return $entry;
    }

    /**
     * Record whether a member attended a day's standup.
     *
     * Leads only — a member may not vouch for their own attendance, and the
     * column is outside `$fillable`, so this is the single write path to it.
     * The row is resolved-or-built, because marking attendance at standup time
     * routinely happens before the member has filled anything in.
     *
     * @param  array{team_id: int|string, user_id: int|string, date: string}  $target
     */
    public function setStandupAttendance(array $target, ?bool $attended, User $actor): TasksheetEntry
    {
        $entry = $this->resolveEntry($target);

        abort_unless($actor->isLead(), 403);
        abort_unless($entry->acceptsStandupAttendance(), 422, 'A full-day leave row has no standup attendance.');

        $entry->standup_attended = $attended;
        $entry->save();

        return $entry;
    }

    /**
     * Whether a membership record covers a given day — the person had not left,
     * been deleted, or been deactivated before it.
     */
    private function coveredDate(User $member, string $dayStr): bool
    {
        $leftAt = $member->pivot->left_at;

        return ($leftAt === null || Carbon::parse($leftAt)->toDateString() >= $dayStr)
            && ($member->deleted_at === null || $member->deleted_at->toDateString() >= $dayStr)
            && ($member->deactivated_at === null || $member->deactivated_at->toDateString() >= $dayStr);
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The resolved state of the planning dashboard: which year and quarter, which
 * project and team, and how the bars are grouped.
 *
 * This exists so the two delivery layers agree on the *defaults*, not just the
 * filters themselves. The quarter rule is the subtle one: no `quarter`
 * parameter at all means "this quarter", while an explicitly empty one is the
 * "All quarters" choice and means the whole year. That distinction is invisible
 * in a plain array of filters and was previously re-derived in both
 * controllers.
 */
class DashboardFilters
{
    public readonly Carbon $rangeStart;

    public readonly Carbon $rangeEnd;

    public function __construct(
        public readonly int $year,
        public readonly ?int $quarter,
        public readonly ?int $projectId,
        public readonly ?int $teamId,
        public readonly string $groupBy,
    ) {
        [$this->rangeStart, $this->rangeEnd] = $this->resolveRange();
    }

    /** Read the dashboard's filters off a request, applying the defaults. */
    public static function fromRequest(Request $request): self
    {
        return new self(
            year: (int) $request->integer('year', (int) now()->year),
            quarter: $request->has('quarter')
                ? ($request->filled('quarter') ? (int) $request->integer('quarter') : null)
                : (int) now()->quarter,
            projectId: $request->filled('project_id') ? (int) $request->integer('project_id') : null,
            teamId: $request->filled('team_id') ? (int) $request->integer('team_id') : null,
            groupBy: $request->input('group_by') === 'project' ? 'project' : 'team',
        );
    }

    public function groupsByProject(): bool
    {
        return $this->groupBy === 'project';
    }

    /** "Q3 2026", or just "2026" when the whole year is in view. */
    public function periodLabel(): string
    {
        return $this->quarter !== null ? 'Q'.$this->quarter.' '.$this->year : (string) $this->year;
    }

    /**
     * The axis: a whole year, or one quarter of it.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(): array
    {
        if ($this->quarter !== null) {
            $start = Carbon::create($this->year, ($this->quarter - 1) * 3 + 1, 1)->startOfDay();

            return [$start, $start->copy()->addMonths(2)->endOfMonth()];
        }

        return [
            Carbon::create($this->year, 1, 1)->startOfDay(),
            Carbon::create($this->year, 12, 31)->endOfDay(),
        ];
    }
}

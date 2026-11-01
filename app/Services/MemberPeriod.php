<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The span a member's analytics cover: this week, month or quarter *to date*,
 * and the same elapsed span of the period before it.
 *
 * Comparing like for like is the point. Nov 1–5 measured against all of
 * October would read as a slump every month until the 20th; measured against
 * Oct 1–5 it is a fair question of "am I ahead of where I was?".
 */
class MemberPeriod
{
    /** @var array<string, string> */
    public const PERIODS = [
        'week' => 'This week',
        'month' => 'This month',
        'quarter' => 'This quarter',
    ];

    public const DEFAULT = 'month';

    public readonly Carbon $start;

    public readonly Carbon $end;

    public readonly Carbon $previousStart;

    public readonly Carbon $previousEnd;

    public function __construct(public readonly string $key, ?Carbon $today = null)
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        $this->start = match ($key) {
            'week' => $today->copy()->startOfWeek(),
            'quarter' => $today->copy()->firstOfQuarter(),
            default => $today->copy()->startOfMonth(),
        };
        $this->end = $today->copy()->endOfDay();

        $this->previousStart = match ($key) {
            'week' => $this->start->copy()->subWeek(),
            'quarter' => $this->start->copy()->subMonthsNoOverflow(3),
            default => $this->start->copy()->subMonthNoOverflow(),
        };

        // Same number of elapsed days, clamped so a 31st never spills past a
        // shorter previous month into the current period.
        $elapsed = (int) $this->start->diffInDays($today);
        $this->previousEnd = $this->previousStart->copy()->addDays($elapsed)->endOfDay()
            ->min($this->start->copy()->subDay()->endOfDay());
    }

    /** Read `?period=`, falling back to the default for anything unknown. */
    public static function fromRequest(Request $request, ?Carbon $today = null): self
    {
        $key = (string) $request->query('period', self::DEFAULT);

        return new self(array_key_exists($key, self::PERIODS) ? $key : self::DEFAULT, $today);
    }

    /** "This month" */
    public function label(): string
    {
        return self::PERIODS[$this->key];
    }

    /** "last month" — the comparison's noun. */
    public function previousLabel(): string
    {
        return 'last '.$this->key;
    }
}

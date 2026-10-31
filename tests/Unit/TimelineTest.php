<?php

namespace Tests\Unit;

use App\Support\Timeline;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class TimelineTest extends TestCase
{
    private function d(string $s): Carbon
    {
        return Carbon::parse($s)->startOfDay();
    }

    public function test_full_range_is_100_percent(): void
    {
        $seg = Timeline::segment(
            $this->d('2026-01-01'), $this->d('2026-12-31'),
            $this->d('2026-01-01'), $this->d('2026-12-31')
        );

        $this->assertTrue($seg['visible']);
        $this->assertEqualsWithDelta(0.0, $seg['offset'], 0.01);
        $this->assertEqualsWithDelta(100.0, $seg['width'], 0.01);
    }

    public function test_middle_window_is_offset(): void
    {
        // Jan (31 days) into a 365-day year → offset ~ 8.49%.
        $seg = Timeline::segment(
            $this->d('2026-02-01'), $this->d('2026-02-28'),
            $this->d('2026-01-01'), $this->d('2026-12-31')
        );

        $this->assertEqualsWithDelta(31 / 365 * 100, $seg['offset'], 0.01);
        $this->assertEqualsWithDelta(28 / 365 * 100, $seg['width'], 0.01);
    }

    public function test_window_outside_range_is_not_visible(): void
    {
        $seg = Timeline::segment(
            $this->d('2025-01-01'), $this->d('2025-06-30'),
            $this->d('2026-01-01'), $this->d('2026-12-31')
        );

        $this->assertFalse($seg['visible']);
    }

    public function test_window_is_clipped_to_range(): void
    {
        // Starts before the range → offset clamps to 0, width covers only the in-range part.
        $seg = Timeline::segment(
            $this->d('2025-12-01'), $this->d('2026-01-31'),
            $this->d('2026-01-01'), $this->d('2026-12-31')
        );

        $this->assertEqualsWithDelta(0.0, $seg['offset'], 0.01);
        $this->assertEqualsWithDelta(31 / 365 * 100, $seg['width'], 0.01);
        $this->assertLessThanOrEqual(100.0, $seg['offset'] + $seg['width']);
    }

    public function test_month_columns_cover_the_year(): void
    {
        $cols = Timeline::monthColumns($this->d('2026-01-01'), $this->d('2026-12-31'));

        $this->assertCount(12, $cols);
        $this->assertSame('Jan 2026', $cols[0]['label']);
        $this->assertSame('Dec 2026', $cols[11]['label']);
    }
}

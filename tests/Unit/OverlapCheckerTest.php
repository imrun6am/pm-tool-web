<?php

namespace Tests\Unit;

use App\Services\OverlapChecker;
use PHPUnit\Framework\TestCase;

class OverlapCheckerTest extends TestCase
{
    public function test_disjoint_windows_do_not_overlap(): void
    {
        // A ends Jul 30, B starts Aug 1 — the exact case in the requirements.
        $this->assertFalse(
            OverlapChecker::windowsOverlap('2026-07-10', '2026-07-30', '2026-08-01', '2026-08-20')
        );
    }

    public function test_touching_windows_overlap_on_shared_day(): void
    {
        // Sharing a single day (Jul 30) counts as an overlap.
        $this->assertTrue(
            OverlapChecker::windowsOverlap('2026-07-10', '2026-07-30', '2026-07-30', '2026-08-20')
        );
    }

    public function test_partial_overlap(): void
    {
        $this->assertTrue(
            OverlapChecker::windowsOverlap('2026-07-10', '2026-07-30', '2026-07-20', '2026-08-05')
        );
    }

    public function test_contained_window_overlaps(): void
    {
        $this->assertTrue(
            OverlapChecker::windowsOverlap('2026-07-01', '2026-07-31', '2026-07-10', '2026-07-20')
        );
    }

    public function test_adjacent_next_day_does_not_overlap(): void
    {
        $this->assertFalse(
            OverlapChecker::windowsOverlap('2026-07-10', '2026-07-30', '2026-07-31', '2026-08-10')
        );
    }
}

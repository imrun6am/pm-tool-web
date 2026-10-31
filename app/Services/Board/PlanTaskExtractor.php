<?php

namespace App\Services\Board;

/** Splits a free-text daily plan into individual board tasks. */
interface PlanTaskExtractor
{
    /**
     * Only work that isn't already on the board comes back: $existing lists the
     * member's current task titles, and plan items describing the same work —
     * even worded differently — are left out and counted in "covered".
     *
     * @param  string  $plan  plain-text plan (one or more lines)
     * @param  string|null  $tickets  plain-text ticket list, used as context only
     * @param  list<string>  $existing  titles of the member's tasks already on the board
     * @return array{tasks: list<array{title: string, description: ?string}>, covered: int}
     */
    public function extract(string $plan, ?string $tickets = null, array $existing = []): array;
}

<?php

namespace App\Services\Board;

/** Splits a free-text daily plan into board tasks and their subtasks. */
interface PlanTaskExtractor
{
    /**
     * Only work that isn't already on the board comes back: $existing lists the
     * member's current tasks, and plan items describing the same work — even
     * worded differently — are left out and counted in "covered". New sub-items
     * of a task in $existing or $teamTasks come back under that task's exact title.
     *
     * @param  string  $plan  plain-text plan (one or more lines, sub-items indented)
     * @param  string|null  $tickets  plain-text ticket list, used as context only
     * @param  list<array{title: string, subtasks: list<string>}>  $existing  the member's open main tasks on the board
     * @param  list<string>  $teamTasks  titles of other open main tasks the new work could belong under
     * @return array{tasks: list<array{title: string, description: ?string, subtasks: list<array{title: string, description: ?string}>}>, covered: int}
     */
    public function extract(string $plan, ?string $tickets = null, array $existing = [], array $teamTasks = []): array;
}

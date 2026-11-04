<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;
use App\Services\TasksheetService;

/** A lead's view of one team. Output, not scores: performance data stays on its own pages. */
class TeamOverview extends ReadTool
{
    public function __construct(private readonly TasksheetService $tasksheet) {}

    public function name(): string
    {
        return 'team_overview';
    }

    public function description(): string
    {
        return "A team at a glance for a lead: tasks by status, overdue and unassigned work, each member's open load, who has not filled today's tasksheet, and its ongoing releases. Without a team id it covers every team this lead is on, or asks for one.";
    }

    public function status(): string
    {
        return 'Looking at the team';
    }

    public function availableTo(User $user): bool
    {
        return $user->isLead();
    }

    public function properties(): array
    {
        return ['team_id' => ['type' => 'integer']];
    }

    public function run(array $input, User $user): ToolOutput
    {
        if (! $user->isLead()) {
            $this->fail('Only leads can see a team overview.');
        }

        $teams = filled($input['team_id'] ?? null)
            ? Team::whereKey((int) $input['team_id'])->get()
            : Team::active()->whereIn('id', $user->currentTeamIds())->orderBy('name')->get();
        if ($teams->isEmpty()) {
            return new ToolOutput('No team given and this lead is on none. Use list_teams_and_releases to find one.');
        }

        $sections = [];
        $refs = [];
        foreach ($teams->take(5) as $team) {
            $tasks = Task::counted()->whereNull('parent_id')->where('team_id', $team->id)->with(['assignee:id,name', 'release:id,name', 'team:id,name'])->get();
            $open = $tasks->whereNotIn('status', Task::DONE_STATUSES);
            $overdue = $open->filter(fn (Task $t) => $t->due_date?->isPast());
            $unassigned = $open->whereNull('assignee_id');

            $people = $team->members()->active()->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn (User $m) => sprintf('%s (id %d): %d open, %d overdue.', $m->name, $m->id,
                    $open->where('assignee_id', $m->id)->count(), $overdue->where('assignee_id', $m->id)->count()));

            $entries = $this->tasksheet->entriesFor($team, today());
            $missing = $this->tasksheet->rowUsersFor($team, today(), $entries)
                ->reject(fn (User $u) => filled($entries->get($u->id)?->plan) || $entries->get($u->id)?->isFullDayLeave())
                ->pluck('name');

            $releases = Release::ongoing()->where('team_id', $team->id)->orderBy('end_date')->limit(5)->get();
            foreach ($releases as $release) {
                $refs["R{$release->id}"] = self::releaseRef($release);
            }

            $list = function ($set) use (&$refs) {
                return $set->take(10)->map(function (Task $t) use (&$refs) {
                    [$line, $ref] = self::taskLine($t);
                    $refs[$ref['ref']] = $ref;

                    return $line;
                })->implode("\n");
            };

            $sections[] = implode("\n", array_filter([
                "Team {$team->name} (id {$team->id}).",
                'Tasks by status: '.($tasks->countBy(fn (Task $t) => $t->statusLabel())->map(fn ($n, $s) => "{$s} {$n}")->implode(', ') ?: 'none').'.',
                'Members:', $people->implode("\n") ?: 'None.',
                'Today’s tasksheet not filled by: '.($missing->implode(', ') ?: 'nobody').'.',
                'Ongoing releases: '.($releases->map(fn (Release $r) => "[R{$r->id}] {$r->name} (ends {$r->end_date->toDateString()})")->implode(', ') ?: 'none').'.',
                "Overdue ({$overdue->count()}):", $list($overdue->sortBy('due_date')),
                "Unassigned ({$unassigned->count()}):", $list($unassigned),
            ], fn ($part) => $part !== ''));
        }

        return new ToolOutput(implode("\n\n", $sections), $refs);
    }
}

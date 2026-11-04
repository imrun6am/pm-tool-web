<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;
use App\Services\QuickCaptureService;

class ListTeamsAndReleases extends ReadTool
{
    public function __construct(private readonly QuickCaptureService $options) {}

    public function name(): string
    {
        return 'list_teams_and_releases';
    }

    public function description(): string
    {
        return 'The active teams and ongoing releases this person may file work into, with their ids. Use it to turn a team or release name into an id.';
    }

    public function status(): string
    {
        return 'Looking up teams and releases';
    }

    public function properties(): array
    {
        return [];
    }

    public function run(array $input, User $user): ToolOutput
    {
        $options = $this->options->options($user);
        $mine = $user->currentTeamIds();

        $teams = $options['teams']->map(fn (Team $t) => "{$t->name} (id {$t->id})".($mine->contains($t->id) ? ', their team' : ''));
        $releases = $options['releases']->take(40)->values();

        return new ToolOutput(
            "Teams:\n".($teams->implode("\n") ?: 'None.')
                ."\n\nOngoing releases:\n".($releases->map(fn (Release $r) => "[R{$r->id}] {$r->name} (id {$r->id}), team id {$r->team_id}, {$r->start_date->toDateString()} to {$r->end_date->toDateString()}")->implode("\n") ?: 'None.'),
            $releases->mapWithKeys(fn (Release $r) => ["R{$r->id}" => self::releaseRef($r)])->all(),
        );
    }
}

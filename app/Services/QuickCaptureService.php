<?php

namespace App\Services;

use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Quick capture: add a task from anywhere in a few keystrokes. The choices it
 * offers are the board's — developers and QA see only their own teams'
 * releases, teams and teammates — and creation is TaskService::create, so a
 * captured task follows the same team and position rules as any other.
 */
class QuickCaptureService
{
    public function __construct(private readonly TaskService $tasks) {}

    /**
     * What the capture form may offer this user.
     *
     * @return array{assignees: Collection<int, User>, releases: Collection<int, Release>, teams: Collection<int, Team>}
     */
    public function options(User $viewer): array
    {
        $limited = $viewer->hasLimitedAccess();
        $teamIds = $limited ? $viewer->currentTeamIds() : null;

        return [
            'assignees' => User::active()
                ->when($limited, fn ($q) => $q->teammatesOf($viewer))
                ->orderBy('name')->get(),
            'releases' => Release::ongoing()
                ->when($limited, fn ($q) => $q->whereIn('team_id', $teamIds))
                ->orderBy('year', 'desc')->orderBy('name')->get(),
            'teams' => Team::active()
                ->when($limited, fn ($q) => $q->whereIn('id', $teamIds))
                ->orderBy('name')->get(),
        ];
    }

    /**
     * A To Do task, assigned to the author unless the request names someone
     * (an explicit null leaves it unassigned).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function capture(array $attributes, User $author): Task
    {
        $attributes = array_key_exists('assignee_id', $attributes)
            ? $attributes
            : ['assignee_id' => $author->id] + $attributes;

        return $this->tasks->create($attributes + ['status' => TaskService::DEFAULT_STATUS], $author);
    }
}

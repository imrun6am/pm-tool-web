<?php

namespace App\Http\Requests\Concerns;

use App\Models\Release;
use App\Models\User;

/**
 * Developers and QA don't choose a task's team: work they file goes to their
 * current team, whatever team_id they send. Someone on several teams picks one
 * of their own; someone on none files teamless (personal) tasks. They may only
 * link their own teams' releases and add their own teammates as assignees. Leads and
 * viewers are untouched.
 */
trait KeepsTasksInViewerTeams
{
    /**
     * Call from prepareForValidation(). On a new task the team is always set
     * for a one-team (or no-team) member; on an edit only when one is sent.
     */
    protected function pinViewerTeam(bool $creating): void
    {
        $user = $this->user();
        if (! $user?->hasLimitedAccess()) {
            return;
        }

        $teamIds = $user->currentTeamIds();
        if ($teamIds->count() <= 1 && ($creating || $this->has('team_id'))) {
            $this->merge(['team_id' => $teamIds->first()]);
        }
    }

    /**
     * Rules added for developers and QA, merged into the request's own.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    protected function withViewerTeamRules(array $rules, bool $creating): array
    {
        $user = $this->user();
        if (! $user?->hasLimitedAccess()) {
            return $rules;
        }

        $teamIds = $user->currentTeamIds()->all();
        $task = $this->route('task');

        // Keeping a task's current release or team is always allowed (a task
        // from another team may be assigned to them); changing it is not.
        $rules['release_id'][] = function (string $attribute, mixed $value, \Closure $fail) use ($teamIds, $task) {
            if ($value !== null && (int) $value !== $task?->release_id
                && ! Release::whereKey((int) $value)->whereIn('team_id', $teamIds)->exists()) {
                $fail('Choose one of your team’s releases.');
            }
        };
        $rules['team_id'][] = function (string $attribute, mixed $value, \Closure $fail) use ($teamIds, $task) {
            if ($value !== null && (int) $value !== $task?->team_id && ! in_array((int) $value, $teamIds, true)) {
                $fail('Choose one of your teams.');
            }
        };
        if ($creating && count($teamIds) > 1) {
            $rules['team_id'][] = 'required_without:release_id';
        }
        foreach (['assignee_id', 'assignee_ids.*'] as $field) {
            if (isset($rules[$field])) {
                $rules[$field][] = $this->assigneeIsTeammate(...);
            }
        }

        return $rules;
    }

    /**
     * A developer or QA may add only themselves or a current teammate; people
     * already on the task may stay.
     */
    private function assigneeIsTeammate(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value === null || $this->route('task')?->hasAssignee((int) $value)) {
            return; // unassigned, or already assigned
        }

        if (! User::teammatesOf($this->user())->whereKey((int) $value)->exists()) {
            $fail('You can only assign yourself or a teammate.');
        }
    }
}

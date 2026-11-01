<?php

namespace App\Models\Concerns;

use App\Models\Release;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Release-linked, authored records (events, meeting notes) that a developer
 * or QA may see beyond the ones they created or attend: those linked to one
 * of their teams' releases, and unlinked ones with no attendees created by a
 * teammate or by a lead or viewer (an organisation-wide entry such as a
 * holiday). An unlinked record with attendees is for its attendees only.
 */
trait LimitsToViewerTeams
{
    protected static function whereSharedWithTeams(Builder $query, User $user, string $authorColumn, string $authorRelation): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('release_id', Release::query()->visibleTo($user)->select('id'))
            ->orWhere(fn (Builder $unlinked) => $unlinked
                ->whereNull('release_id')
                ->whereDoesntHave('attendees')
                ->where(fn (Builder $by) => $by
                    ->whereIn($authorColumn, User::query()->teammatesOf($user)->select('users.id'))
                    ->orWhereHas($authorRelation, fn (Builder $a) => $a->whereNotIn('role', User::LIMITED_ROLES)))));
    }
}

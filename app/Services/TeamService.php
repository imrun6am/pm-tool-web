<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Teams and their membership.
 *
 * Membership is never erased. Removing someone stamps `left_at` on the pivot so
 * the team's past tasksheets and performance records still know they were
 * there — deleting the row would silently rewrite history. Re-adding them
 * clears the stamp rather than creating a second membership.
 */
class TeamService
{
    /**
     * @param  array{status?: ?string, search?: ?string}  $filters
     * @return Builder<Team>
     */
    public function filtered(array $filters = []): Builder
    {
        return Team::query()
            ->with('teamLead')
            ->withCount(['releases', 'members'])
            ->when(($filters['status'] ?? null) === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderByRaw('archived_at is not null') // active first
            ->orderBy('name');
    }

    /**
     * Add a member, restoring a previous membership rather than duplicating it.
     */
    public function addMember(Team $team, int|string $userId): User
    {
        $team->memberRecords()->syncWithoutDetaching([$userId => ['left_at' => null]]);

        return User::findOrFail($userId);
    }

    /** Soft leave: stamp the departure, keep the row. */
    public function removeMember(Team $team, User $user): void
    {
        $team->memberRecords()->updateExistingPivot($user->id, ['left_at' => now()]);
    }

    /** Assign or clear the team lead — any active user, regardless of role. */
    public function updateLead(Team $team, int|string|null $userId): Team
    {
        $team->update(['team_lead_id' => $userId ?: null]);

        return $team->load('teamLead');
    }

    /**
     * Active users not already on the team, for the "add member" picker.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(Team $team): Collection
    {
        $currentIds = $team->relationLoaded('members')
            ? $team->members->pluck('id')
            : $team->members()->pluck('users.id');

        return User::active()
            ->whereNotIn('id', $currentIds)
            ->orderBy('name')
            ->get();
    }

    /** Any active user may lead a team — role is irrelevant. */
    public function leadCandidates(): Collection
    {
        return User::active()->orderBy('name')->get();
    }

    /**
     * A team that owns releases is archived, never hard-deleted — deleting it
     * would cascade away release history.
     */
    public function isDeletable(Team $team): bool
    {
        return ! $team->releases()->exists();
    }

    public function archive(Team $team): Team
    {
        $team->update(['archived_at' => now()]);

        return $team;
    }

    public function restore(Team $team): Team
    {
        $team->update(['archived_at' => null]);

        return $team;
    }
}

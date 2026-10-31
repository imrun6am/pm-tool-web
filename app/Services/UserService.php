<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * User administration.
 *
 * The guard rails here protect against states the system cannot recover from:
 * you may not lock yourself out, and you may not remove or demote the last
 * active administrator. They are predicates rather than exceptions so each
 * delivery layer can answer in its own idiom — a flash message on the web, a
 * 422 over the API.
 */
class UserService
{
    /**
     * The user directory, active accounts first.
     *
     * @param  array{search?: ?string, role?: ?string, status?: ?string}  $filters
     * @return Builder<User>
     */
    public function directory(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = $filters['role'] ?? null;
        $status = $filters['status'] ?? null;

        return User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($role && array_key_exists($role, User::ROLES), fn ($q) => $q->where('role', $role))
            ->when($status === 'active', fn ($q) => $q->whereNull('deactivated_at'))
            ->when($status === 'inactive', fn ($q) => $q->whereNotNull('deactivated_at'))
            ->orderByRaw('deactivated_at is not null') // active first
            ->orderBy('name');
    }

    /**
     * Directory-wide totals and the role distribution. Deliberately independent
     * of the filters, so the overview describes the whole organisation rather
     * than whatever the list happens to be showing.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $everyone = User::query()->get(['id', 'role', 'deactivated_at']);

        return [
            'total' => $everyone->count(),
            'active' => $everyone->whereNull('deactivated_at')->count(),
            'inactive' => $everyone->whereNotNull('deactivated_at')->count(),
            'engineers' => $everyone->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])->count(),
            'roleDistribution' => collect(User::ROLES)
                ->map(fn (string $label, string $role) => [
                    'role' => $role,
                    'label' => $label,
                    'count' => $everyone->where('role', $role)->count(),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    /**
     * Update an account. A blank password means "keep the current one" — the
     * field is dropped rather than hashed as an empty string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        $user->update($attributes);

        return $user;
    }

    /**
     * Deactivate an account and cut its API tokens, so a desktop client loses
     * access at once rather than on its next call.
     */
    public function deactivate(User $user): User
    {
        $user->update(['deactivated_at' => now()]);
        $user->tokens()->delete();

        return $user;
    }

    public function reactivate(User $user): User
    {
        $user->update(['deactivated_at' => null]);

        return $user;
    }

    /**
     * Soft delete: the account can no longer sign in and drops out of listings,
     * but everything it produced stays visible, tagged "Deleted user". Team
     * memberships are stamped as ended now, so sheets after today stop
     * expecting the person.
     */
    public function softDelete(User $user): void
    {
        $user->teams->each(
            fn (Team $team) => $team->memberRecords()->updateExistingPivot($user->id, ['left_at' => now()])
        );

        $user->tokens()->delete();
        $user->delete(); // the model's deleting hook also vacates any team they lead
    }

    /** Whether the acting user is looking at their own account. */
    public function isSelf(User $actor, User $target): bool
    {
        return $actor->id === $target->id;
    }

    /**
     * Whether this account is the only active administrator left — the state
     * that must never be removed, demoted, or deactivated.
     */
    public function isLastActiveAdmin(User $user): bool
    {
        return $user->isAdmin()
            && $user->isActive()
            && User::active()->where('role', User::ROLE_ADMIN)->count() <= 1;
    }

    /** Whether changing this account's role to `$role` would be safe. */
    public function canChangeRoleTo(User $user, string $role): bool
    {
        if ($user->isAdmin() && $role !== User::ROLE_ADMIN) {
            return ! $this->isLastActiveAdmin($user);
        }

        return true;
    }
}

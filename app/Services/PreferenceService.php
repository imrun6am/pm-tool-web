<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

/**
 * Per-user settings kept in `users.preferences` (JSON). Only the keys in
 * DEFAULTS exist; a stored value wins over the default, and an unknown key is
 * refused rather than silently stored.
 */
class PreferenceService
{
    /** Every known preference and its default. */
    public const DEFAULTS = [
        // Show the "nice work" moment when the user finishes a task.
        'celebrations' => true,
        // When the user dismissed the dashboard "Get started" card (ISO string).
        'onboarding_dismissed_at' => null,
    ];

    /** Preferences a user may change directly (Profile page / API). */
    public const EDITABLE = ['celebrations'];

    public function get(User $user, string $key): mixed
    {
        $this->assertKnown($key);

        return array_key_exists($key, $user->preferences ?? [])
            ? $user->preferences[$key]
            : self::DEFAULTS[$key];
    }

    /**
     * Every preference with defaults filled in.
     *
     * @return array<string, mixed>
     */
    public function all(User $user): array
    {
        return array_merge(self::DEFAULTS, array_intersect_key($user->preferences ?? [], self::DEFAULTS));
    }

    /** @param  array<string, mixed>  $values */
    public function set(User $user, array $values): User
    {
        foreach (array_keys($values) as $key) {
            $this->assertKnown($key);
        }

        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], $values)])->save();

        return $user;
    }

    private function assertKnown(string $key): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Unknown preference [{$key}].");
        }
    }
}

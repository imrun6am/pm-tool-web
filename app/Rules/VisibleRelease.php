<?php

namespace App\Rules;

use App\Models\Release;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The release must be one the user may see: any for leads and viewers, only
 * their own teams' for developers and QA. A record's current release may
 * always be kept.
 */
class VisibleRelease implements ValidationRule
{
    public function __construct(private readonly ?User $user, private readonly ?int $keep = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || ! $this->user?->hasLimitedAccess() || (int) $value === $this->keep) {
            return;
        }

        if (! Release::whereKey((int) $value)->visibleTo($this->user)->exists()) {
            $fail('Choose one of your team’s releases.');
        }
    }
}

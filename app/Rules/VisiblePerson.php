<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The person must be one the user may see: anyone for leads and viewers, only
 * teammates (and their teams' leads) for developers and QA. People already on
 * the record may always be kept.
 */
class VisiblePerson implements ValidationRule
{
    /** @param  array<int, int>  $keep */
    public function __construct(private readonly ?User $user, private readonly array $keep = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || ! $this->user?->hasLimitedAccess() || in_array((int) $value, $this->keep, true)) {
            return;
        }

        if (! User::whereKey((int) $value)->visibleTo($this->user)->exists()) {
            $fail('You can only choose people on your team.');
        }
    }
}

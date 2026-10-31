<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A saved bookmark shown in the quick-links drawer — private to its author or
 * shared. Limited roles (developer/QA) are private-only: they neither see
 * others' shared links nor create shared ones.
 */
class QuickLink extends Model
{
    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SHARED = 'shared';

    /** @var array<string, string> */
    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE => 'Private',
        self::VISIBILITY_SHARED => 'Shared',
    ];

    protected $fillable = ['user_id', 'release_id', 'label', 'url', 'visibility'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Users who have pinned this link. A pin is per-viewer: pinning a shared
     * link changes nothing for anyone else, which is why it lives on a pivot
     * rather than a column on the link itself.
     */
    public function pinnedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function isShared(): bool
    {
        return $this->visibility === self::VISIBILITY_SHARED;
    }

    /**
     * Whether $user has pinned this link. Prefers the `is_pinned` column the
     * drawer query selects for the viewer, then a loaded relation, and only
     * falls back to a query when neither is present — so a listing never
     * issues one query per row.
     */
    public function isPinnedBy(User $user): bool
    {
        if (array_key_exists('is_pinned', $this->attributes)) {
            return (bool) $this->attributes['is_pinned'];
        }

        return $this->relationLoaded('pinnedBy')
            ? $this->pinnedBy->contains($user->id)
            : $this->pinnedBy()->whereKey($user->id)->exists();
    }

    /**
     * Links the given user may see: their own — plus, for full-access roles
     * only, everyone's shared links. Limited roles see nothing but their own.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasLimitedAccess()) {
            return $query->where('user_id', $user->id);
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', self::VISIBILITY_SHARED)
                ->orWhere('user_id', $user->id);
        });
    }
}

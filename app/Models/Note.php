<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\HtmlString;

class Note extends Model
{
    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SHARED = 'shared';

    public const VISIBILITY_SPECIFIC = 'specific';

    /** @var array<string, string> */
    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE => 'Private — only me',
        self::VISIBILITY_SHARED => 'Shared — everyone',
        self::VISIBILITY_SPECIFIC => 'Specific people',
    ];

    protected $fillable = ['user_id', 'date', 'body', 'visibility'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** Rich-text body is stored as sanitized HTML (from the Trix editor). */
    protected function body(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => HtmlSanitizer::clean($value),
        );
    }

    public function bodyHtml(): HtmlString
    {
        return new HtmlString($this->body ?? '');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** Users a "specific" note is shared with. */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'note_user')->withTimestamps()->withTrashed();
    }

    public function isShared(): bool
    {
        return $this->visibility === self::VISIBILITY_SHARED;
    }

    public function isSpecific(): bool
    {
        return $this->visibility === self::VISIBILITY_SPECIFIC;
    }

    public function visibilityLabel(): string
    {
        return self::VISIBILITIES[$this->visibility] ?? ucfirst($this->visibility);
    }

    /** Short badge label for a note card. */
    public function visibilityBadge(): string
    {
        return match ($this->visibility) {
            self::VISIBILITY_SHARED => 'Shared',
            self::VISIBILITY_SPECIFIC => 'Specific people',
            default => 'Private',
        };
    }

    /**
     * Whether the given user may see this note: shared → everyone; specific →
     * the author and the people it's shared with; private → the author only.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->user_id === $user->id || $this->isShared()) {
            return true;
        }
        if ($this->isSpecific()) {
            return $this->relationLoaded('recipients')
                ? $this->recipients->contains($user->id)
                : $this->recipients()->whereKey($user->id)->exists();
        }

        return false;
    }

    /**
     * Notes the given user may see: everyone's shared notes, their own, and
     * "specific" notes shared with them. Other users' private (and specific
     * notes they were not shared with) are never included.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', self::VISIBILITY_SHARED)
                ->orWhere('user_id', $user->id)
                ->orWhere(fn (Builder $q) => $q
                    ->where('visibility', self::VISIBILITY_SPECIFIC)
                    ->whereHas('recipients', fn (Builder $r) => $r->whereKey($user->id)));
        });
    }
}

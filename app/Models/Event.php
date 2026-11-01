<?php

namespace App\Models;

use App\Models\Concerns\LimitsToViewerTeams;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Event extends Model
{
    use LimitsToViewerTeams;
    use RecordsActivity {
        recordActivity as private writeActivity;
    }

    /** Seen by everyone the team-scoping rules allow (the default). */
    public const VISIBILITY_SHARED = 'shared';

    /** Seen only by the creator and the attendees they invited. */
    public const VISIBILITY_PRIVATE = 'private';

    /** @var array<string, string> */
    public const VISIBILITIES = [
        self::VISIBILITY_SHARED => 'Shared',
        self::VISIBILITY_PRIVATE => 'Private',
    ];

    protected $fillable = [
        'title', 'description', 'type', 'starts_at', 'ends_at', 'all_day',
        'location', 'release_id', 'created_by', 'visibility',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'visibility' => self::VISIBILITY_SHARED,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    /** @var array<string, string> */
    public const TYPES = [
        'meeting' => 'Meeting',
        'review' => 'Review',
        'release' => 'Release',
        'deadline' => 'Deadline',
        'other' => 'Other',
    ];

    /** @var array<string, string> */
    public const TYPE_COLORS = [
        'meeting' => '#6366f1',  // indigo
        'review' => '#0891b2',   // cyan
        'release' => '#10b981',  // emerald
        'deadline' => '#e11d48', // rose
        'other' => '#64748b',    // slate
    ];

    /**
     * Events the user may see. Everyone sees events they created or attend.
     * Beyond those, only shared events: all of them for leads and viewers,
     * and for developers and QA those shared with their teams (see
     * LimitsToViewerTeams). Nobody else sees a private event — admins included.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('created_by', $user->id)
            ->orWhereHas('attendees', fn (Builder $a) => $a->whereKey($user->id))
            ->orWhere(fn (Builder $shared) => $shared
                ->where('visibility', self::VISIBILITY_SHARED)
                ->when($user->hasLimitedAccess(), fn (Builder $team) => static::whereSharedWithTeams($team, $user, 'created_by', 'creator'))));
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->created_by === $user->id) {
            return true;
        }
        if (! $this->isPrivate() && ! $user->hasLimitedAccess()) {
            return true;
        }

        return static::whereKey($this->getKey())->visibleTo($user)->exists();
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    public function visibilityLabel(): string
    {
        return self::VISIBILITIES[$this->visibility] ?? ucfirst((string) $this->visibility);
    }

    /**
     * The activity feed is readable by every lead, so a private event never
     * writes to it. Entries from while it was shared are left as they were.
     */
    public function recordActivity(string $event): void
    {
        if (! $this->isPrivate()) {
            $this->writeActivity($event);
        }
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** Meeting notes written from this event, newest meeting first. */
    public function meetingNotes(): HasMany
    {
        return $this->hasMany(MeetingNote::class)->orderByDesc('meeting_date')->orderByDesc('id');
    }

    public function isMeeting(): bool
    {
        return $this->type === 'meeting';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function typeColor(): string
    {
        return self::TYPE_COLORS[$this->type] ?? '#64748b';
    }

    public function endOrStart(): Carbon
    {
        return $this->ends_at ?? $this->starts_at;
    }

    public function isMultiDay(): bool
    {
        return $this->starts_at->toDateString() !== $this->endOrStart()->toDateString();
    }

    /**
     * Y-m-d strings for each day this event covers, clipped to [$from, $to] if given.
     *
     * @return array<int, string>
     */
    public function coveredDates(?Carbon $from = null, ?Carbon $to = null): array
    {
        $start = $this->starts_at->copy()->startOfDay();
        $end = $this->endOrStart()->copy()->startOfDay();

        if ($from && $start->lt($from)) {
            $start = $from->copy()->startOfDay();
        }
        if ($to && $end->gt($to)) {
            $end = $to->copy()->startOfDay();
        }

        $dates = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    public function timeLabel(): string
    {
        if ($this->all_day) {
            return 'All day';
        }

        $out = $this->starts_at->format('g:i a');
        if ($this->ends_at) {
            $out .= ' – '.$this->ends_at->format('g:i a');
        }

        return $out;
    }

    public function activityTitle(): string
    {
        return $this->title;
    }

    public function activityReleaseId(): ?int
    {
        return $this->release_id;
    }

    protected function activityExtraIgnored(): array
    {
        return ['created_by'];
    }
}

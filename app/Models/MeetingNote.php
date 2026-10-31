<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\HtmlString;

/**
 * Minutes of a meeting — either linked to a release ("release-wise") or
 * general (no release). Records its attendees and a visibility of `everyone`
 * (default) or `attendees` (visible only to attendees, author, and leads).
 *
 * The kind of meeting is a `type` drawn from TYPES below. Adding, relabeling,
 * or retiring a type is an edit to that constant — no migration, and no client
 * release either, since MetaController publishes the list at /v1/meta.
 */
class MeetingNote extends Model
{
    use RecordsActivity;

    public const VISIBILITY_EVERYONE = 'everyone';

    public const VISIBILITY_ATTENDEES = 'attendees';

    /** @var array<string, string> */
    public const VISIBILITIES = [
        self::VISIBILITY_EVERYONE => 'Everyone',
        self::VISIBILITY_ATTENDEES => 'Attendees only',
    ];

    /**
     * The general catch-all type. It is the column default, so notes written
     * before types existed are all of this type, and it keeps the label they
     * already read as — nothing looks re-categorized.
     */
    public const TYPE_DEFAULT = 'meeting';

    /**
     * The kinds of meeting a note can record. Add or relabel entries here;
     * prefer relabeling over removal, since a removed type's notes stay
     * readable but drop out of the type filter.
     *
     * Ordering is meaningful: it is the order the form selector, the type
     * filter, and every API client's picker render in — the recurring cadence
     * meetings first, then the delivery milestones in the order they happen.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        self::TYPE_DEFAULT => 'Meeting',
        'sync_up' => 'Sync Up',
        'day_end' => 'Day End',
        'progress_monitoring' => 'Progress Monitoring',
        'initial_handover' => 'Initial Handover',
        'initial_qa_feedback' => 'Initial QA Feedback',
        'final_handover' => 'Final Handover',
    ];

    /** @var array<string, string> */
    public const TYPE_COLORS = [
        self::TYPE_DEFAULT => '#6366f1',      // indigo
        'sync_up' => '#0891b2',               // cyan
        'day_end' => '#f59e0b',               // amber
        'progress_monitoring' => '#ec4899',   // pink
        'initial_handover' => '#8b5cf6',      // violet
        'initial_qa_feedback' => '#10b981',   // emerald
        'final_handover' => '#7c3aed',        // deeper violet — pairs with the initial handover
    ];

    protected $fillable = ['release_id', 'event_id', 'created_by', 'title', 'type', 'meeting_date', 'body', 'visibility'];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
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

    /**
     * Body HTML for list/card previews. Keeps the rich formatting (bold, lists,
     * headings…) but strips anchor tags — the preview lives inside the card's own
     * <a>, and nesting anchors is invalid HTML. Link text is preserved.
     */
    public function bodyPreviewHtml(): HtmlString
    {
        $html = preg_replace('/<a\b[^>]*>|<\/a>/i', '', $this->body ?? '');

        return new HtmlString($html ?? '');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** The calendar meeting this note was written from, if any. */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Users recorded as having attended this meeting. */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withTrashed();
    }

    public function isAttendeesOnly(): bool
    {
        return $this->visibility === self::VISIBILITY_ATTENDEES;
    }

    /**
     * Falls back to the raw value so a note keeps rendering after its type is
     * retired from TYPES, rather than showing a blank badge.
     */
    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function typeColor(): string
    {
        return self::TYPE_COLORS[$this->type] ?? '#64748b';
    }

    public function visibilityLabel(): string
    {
        return self::VISIBILITIES[$this->visibility] ?? ucfirst((string) $this->visibility);
    }

    /**
     * Whether the given user may view this note. Everyone-notes are public;
     * attendees-only notes are limited to their attendees, author, and leads.
     */
    public function isVisibleTo(User $user): bool
    {
        if (! $this->isAttendeesOnly() || $this->created_by === $user->id || $user->isLead()) {
            return true;
        }

        // Use the loaded collection when available; otherwise a targeted query.
        return $this->relationLoaded('attendees')
            ? $this->attendees->contains($user->id)
            : $this->attendees()->whereKey($user->id)->exists();
    }

    /**
     * Restrict a query to notes the user may view (see isVisibleTo). Leads and
     * authors always pass; other users see everyone-notes plus ones they attended.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isLead()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', self::VISIBILITY_EVERYONE)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('attendees', fn (Builder $a) => $a->whereKey($user->id));
        });
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForRelease(Builder $query, int $releaseId): Builder
    {
        return $query->where('release_id', $releaseId);
    }

    /** Notes not linked to any release. */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereNull('release_id');
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
        // The sanitized HTML body is too noisy for the activity log.
        return ['created_by', 'body'];
    }
}

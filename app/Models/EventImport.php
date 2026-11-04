<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * "Create events from a file": an uploaded document, the events Claude (or the
 * ICS parser) proposed from it, and how far the review got. The proposals are
 * a private draft held on this row; nothing reaches `events` until the
 * uploader confirms, and each confirmed proposal remembers its event id so a
 * reopened import can never add it twice.
 *
 * Deliberately no RecordsActivity: the activity feed is shared, an import is not.
 */
class EventImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const INPUT_FILE = 'file';

    public const INPUT_PASTE = 'paste';

    public const INPUT_NOTE = 'note';

    public const SOURCE_ICS = 'ics';

    public const SOURCE_CLAUDE = 'claude';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_PENDING => 'Queued',
        self::STATUS_PROCESSING => 'Reading file',
        self::STATUS_NEEDS_REVIEW => 'Ready to review',
        self::STATUS_COMPLETED => 'Added to calendar',
        self::STATUS_FAILED => 'Failed',
    ];

    protected $fillable = [
        'user_id', 'input', 'original_name', 'preview', 'file_path', 'extension', 'mime_type', 'size', 'timezone', 'status', 'source',
        'origin_type', 'origin_id', 'reference_date',
        'chunks_total', 'chunks_done', 'proposals', 'events_created', 'input_tokens', 'output_tokens', 'error',
        'processed_at', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposals' => 'array',
            'reference_date' => 'date',
            'size' => 'integer',
            'chunks_total' => 'integer',
            'chunks_done' => 'integer',
            'events_created' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'processed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** The meeting note or daily note this import read, if any and if it still exists. */
    public function origin(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    /** Ready for (re-)review: proposals exist and the job is done with them. */
    public function isReviewable(): bool
    {
        return in_array($this->status, [self::STATUS_NEEDS_REVIEW, self::STATUS_COMPLETED], true);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_PROCESSING && $this->isFromNote()) {
            return 'Reading note';
        }

        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_NEEDS_REVIEW => 'badge-warning',
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_FAILED => 'badge-danger',
            default => 'badge-info',
        };
    }

    /** @return list<array<string, mixed>> */
    public function proposalList(): array
    {
        return array_values($this->proposals ?? []);
    }

    public function proposalCount(): int
    {
        return count($this->proposals ?? []);
    }

    /** Proposals not yet turned into events. */
    public function pendingCount(): int
    {
        return collect($this->proposals ?? [])->whereNull('event_id')->count();
    }

    /** Chunks read so far as a 0–100 percentage (0 until the job has split the file). */
    public function progressPercent(): int
    {
        return $this->chunks_total > 0 ? (int) floor($this->chunks_done / $this->chunks_total * 100) : 0;
    }

    public function isPasted(): bool
    {
        return $this->input === self::INPUT_PASTE;
    }

    public function isFromNote(): bool
    {
        return $this->input === self::INPUT_NOTE;
    }

    /**
     * Whether the user may read the note behind this import. An import that
     * did not come from a note has nothing to check; a deleted note can no
     * longer be read by anyone.
     */
    public function originReadableBy(User $user): bool
    {
        return ! $this->isFromNote() || ($this->origin?->isVisibleTo($user) ?? false);
    }

    /** Where the source note can be read, when this viewer may read it. */
    public function originUrlFor(User $user): ?string
    {
        if (! $this->isFromNote() || ! $this->originReadableBy($user)) {
            return null;
        }

        return $this->origin instanceof MeetingNote
            ? route('meeting-notes.show', $this->origin)
            : route('notes.index', ['date' => $this->origin->date->toDateString()]);
    }

    /** The confirmation shown before deleting, naming what goes with the import. */
    public function deleteConfirmation(): string
    {
        return $this->isFromNote()
            ? 'Delete this import? The note and any events already added stay as they are.'
            : 'Delete this import and its '.($this->isPasted() ? 'pasted text' : 'uploaded file').'? Events already added stay on the calendar.';
    }

    /** The file name, "Pasted text" for a paste, or the note's label. */
    public function displayName(): string
    {
        return $this->isPasted() ? 'Pasted text' : $this->original_name;
    }

    public function sizeLabel(): string
    {
        if ($this->isPasted() || $this->isFromNote()) {
            return number_format($this->size).' '.($this->size === 1 ? 'character' : 'characters');
        }

        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : max(1, (int) round($this->size / 1024)).' KB';
    }
}

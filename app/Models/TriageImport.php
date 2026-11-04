<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Create requests from a file, screenshot or text": the source, the requests
 * Claude proposed from it, and how far the review got. The proposals are a
 * private draft held on this row; nothing reaches `triage_items` until the
 * person who started it confirms, and each sent proposal remembers its request
 * so it can't be sent twice.
 *
 * Deliberately no RecordsActivity: the activity feed is shared, an import is not.
 */
class TriageImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const INPUT_FILE = 'file';

    public const INPUT_PASTE = 'paste';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_PENDING => 'Queued',
        self::STATUS_PROCESSING => 'Reading',
        self::STATUS_NEEDS_REVIEW => 'Ready to review',
        self::STATUS_COMPLETED => 'Sent',
        self::STATUS_FAILED => 'Failed',
    ];

    protected $fillable = [
        'user_id', 'input', 'original_name', 'preview', 'file_path', 'extension', 'mime_type', 'size', 'status',
        'chunks_total', 'chunks_done', 'proposals', 'proposals_dropped', 'requests_created', 'error',
        'processed_at', 'confirmed_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => self::STATUS_PENDING, 'input' => self::INPUT_FILE];

    protected function casts(): array
    {
        return [
            'proposals' => 'array',
            'size' => 'integer',
            'user_id' => 'integer',
            'chunks_total' => 'integer',
            'chunks_done' => 'integer',
            'proposals_dropped' => 'integer',
            'requests_created' => 'integer',
            'processed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(TriageItem::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    /** Ready for (re-)review: the job is done with the proposals. */
    public function isReviewable(): bool
    {
        return in_array($this->status, [self::STATUS_NEEDS_REVIEW, self::STATUS_COMPLETED], true);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isPasted(): bool
    {
        return $this->input === self::INPUT_PASTE;
    }

    public function statusLabel(): string
    {
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

    /** Parts read so far as a 0–100 percentage (0 until the job has split the source). */
    public function progressPercent(): int
    {
        return $this->chunks_total > 0 ? (int) floor($this->chunks_done / $this->chunks_total * 100) : 0;
    }

    /** No worker has picked it up for a while, which usually means none is running. */
    public function isStuckInQueue(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->created_at->lt(now()->subMinutes(2));
    }

    /** The file name, or "Pasted text" for a paste. */
    public function displayName(): string
    {
        return $this->isPasted() ? 'Pasted text' : $this->original_name;
    }

    public function sizeLabel(): string
    {
        if ($this->isPasted()) {
            return number_format($this->size).' '.($this->size === 1 ? 'character' : 'characters');
        }

        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : max(1, (int) round($this->size / 1024)).' KB';
    }

    /** The confirmation shown before deleting, naming what goes with the import. */
    public function deleteConfirmation(): string
    {
        $source = $this->isPasted() ? 'pasted text' : 'uploaded file';

        return $this->requests_created > 0
            ? "Delete this import and its {$source}? Requests already sent stay, but no longer link to it."
            : "Delete this import and its {$source}?";
    }
}

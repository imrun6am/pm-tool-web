<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'user_id', 'original_name', 'file_path', 'extension', 'mime_type', 'size', 'timezone', 'status', 'source',
        'chunks_total', 'chunks_done', 'proposals', 'events_created', 'input_tokens', 'output_tokens', 'error',
        'processed_at', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposals' => 'array',
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

    public function sizeLabel(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : max(1, (int) round($this->size / 1024)).' KB';
    }
}

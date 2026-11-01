<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A Claude result generated in the background and attached to the record it
 * describes: a release's risk brief, a team's standup digest for a day, or a
 * release document's summary and suggested tasks.
 */
class AiInsight extends Model
{
    public const KIND_RELEASE_RISK = 'release_risk';

    public const KIND_STANDUP_DIGEST = 'standup_digest';

    public const KIND_DOCUMENT = 'document_insights';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'kind', 'subject_type', 'subject_id', 'for_date', 'status', 'payload', 'error',
        'requested_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'for_date' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /** The JSON the status endpoint and the partials' pollers read. */
    public function toStatus(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'running' => $this->isRunning(),
            'error' => $this->error,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'generated_human' => $this->generated_at?->diffForHumans(),
        ];
    }
}

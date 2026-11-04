<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change the assistant proposed. It sits here until its owner confirms or
 * cancels it; confirming, by id alone, is the only way it runs, so neither the
 * model nor the browser can alter what runs.
 */
class AssistantAction extends Model
{
    public const PENDING = 'pending';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    /** A draft older than this no longer runs. */
    public const EXPIRES_AFTER_HOURS = 24;

    protected $fillable = ['conversation_id', 'user_id', 'tool', 'tool_use_id', 'input', 'preview', 'status', 'result', 'error', 'resolved_at'];

    protected function casts(): array
    {
        return ['input' => 'array', 'preview' => 'array', 'result' => 'array', 'resolved_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    public function isStale(): bool
    {
        return $this->status === self::PENDING && $this->created_at->lt(now()->subHours(self::EXPIRES_AFTER_HOURS));
    }
}

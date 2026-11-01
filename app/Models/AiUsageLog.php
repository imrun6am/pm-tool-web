<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Claude call made through AiGateway: who, which feature, tokens, outcome. */
class AiUsageLog extends Model
{
    public const UPDATED_AT = null;

    public const STATUSES = [
        'ok' => 'OK',
        'refused' => 'Declined',
        'truncated' => 'Cut off',
        'invalid' => 'Unreadable',
        'error' => 'Error',
    ];

    protected $fillable = [
        'feature', 'user_id', 'model', 'status', 'input_tokens', 'output_tokens',
        'cache_read_tokens', 'duration_ms', 'error',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Estimated cost in USD from config('ai.pricing'); null for an unpriced model. */
    public function estimatedCost(): ?float
    {
        return self::cost($this->model, $this->input_tokens, $this->output_tokens, $this->cache_read_tokens);
    }

    public static function cost(string $model, int $input, int $output, int $cacheRead = 0): ?float
    {
        $price = config("ai.pricing.{$model}");
        if (! is_array($price)) {
            return null;
        }

        return ($input * $price['input'] + $output * $price['output'] + $cacheRead * ($price['cache_read'] ?? 0)) / 1_000_000;
    }
}

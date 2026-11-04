<?php

namespace App\Models;

use App\Support\LogRetention;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use MassPrunable;

    /** Rows about these belong to a release's history, even if the backfill couldn't link them because the phase or off day is gone. */
    private const RELEASE_SUBJECTS = [Release::class, ReleasePhase::class, ReleaseOffDay::class];

    protected $fillable = [
        'log_name', 'description', 'event', 'subject_type', 'subject_id', 'causer_id', 'release_id', 'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /** Rows past the retention period; `model:prune` removes them. A release's history stays. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', LogRetention::cutoff())
            ->whereNull('release_id')
            ->whereNotIn('subject_type', self::RELEASE_SUBJECTS);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id')->withTrashed();
    }

    /**
     * For `updated` events: [field => ['old' => ..., 'new' => ...]].
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        $new = $this->properties['attributes'] ?? [];
        $old = $this->properties['old'] ?? [];

        $out = [];
        foreach ($new as $field => $value) {
            $out[$field] = ['old' => $old[$field] ?? null, 'new' => $value];
        }

        return $out;
    }

    public function eventColor(): string
    {
        return match ($this->event) {
            'created' => 'emerald',
            'deleted' => 'rose',
            default => 'indigo',
        };
    }
}

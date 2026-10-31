<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    protected $fillable = [
        'log_name', 'description', 'event', 'subject_type', 'subject_id', 'causer_id', 'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
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

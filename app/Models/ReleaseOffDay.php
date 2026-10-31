<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ReleaseOffDay extends Model
{
    use RecordsActivity;

    protected $fillable = ['release_id', 'date', 'reason'];

    /**
     * Store the off-day as a plain Y-m-d string (not a datetime) and read it back
     * as a Carbon date. This keeps the value the unique-per-release validation
     * compares against identical to what is stored.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function activityTitle(): string
    {
        return $this->date?->format('M j, Y') ?? 'off-day';
    }

    public function activityReleaseId(): ?int
    {
        return $this->release_id;
    }
}

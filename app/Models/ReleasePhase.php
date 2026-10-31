<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleasePhase extends Model
{
    use RecordsActivity;

    protected $fillable = ['release_id', 'phase', 'position', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'position' => 'integer',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function label(): string
    {
        return Release::PHASES[$this->phase] ?? ucfirst($this->phase);
    }

    public function activityTitle(): string
    {
        return $this->label();
    }

    public function activityReleaseId(): ?int
    {
        return $this->release_id;
    }

    protected function activityExtraIgnored(): array
    {
        return ['position'];
    }
}

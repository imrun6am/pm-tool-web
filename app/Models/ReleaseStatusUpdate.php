<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A lead's word on how a release is going, kept as a history. */
class ReleaseStatusUpdate extends Model
{
    use RecordsActivity;

    /** @var array<string, string> */
    public const STATUSES = ['on_track' => 'On track', 'at_risk' => 'At risk', 'off_track' => 'Off track'];

    protected $fillable = ['release_id', 'author_id', 'status', 'note'];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function activityTitle(): string
    {
        return $this->statusLabel();
    }

    public function activityReleaseId(): ?int
    {
        return $this->release_id;
    }
}

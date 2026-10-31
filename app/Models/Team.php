<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'description', 'color', 'team_lead_id', 'archived_at'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /** Current members of this team (people who have left are excluded). */
    public function members(): BelongsToMany
    {
        return $this->memberRecords()->wherePivotNull('left_at');
    }

    /** All membership records, including people who have left (pivot: left_at). */
    public function memberRecords(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot('left_at');
    }

    /** The user leading this team — any user, regardless of role. */
    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}

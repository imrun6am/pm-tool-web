<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'description', 'color', 'archived_at'];

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

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}

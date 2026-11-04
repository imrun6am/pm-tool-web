<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A shared tag for tasks. Anyone can apply one; leads manage the list. */
class Label extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'color'];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }
}

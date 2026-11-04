<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How many open tasks a team wants in a board column at once. A guide, not a block. */
class WipLimit extends Model
{
    use RecordsActivity;

    protected $fillable = ['team_id', 'status', 'max_tasks'];

    protected function casts(): array
    {
        return ['team_id' => 'integer', 'max_tasks' => 'integer'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function activityTitle(): string
    {
        return (Task::STATUSES[$this->status] ?? $this->status).' limit';
    }
}

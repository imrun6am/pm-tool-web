<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One status a task moved into. Task writes these itself on every save, and
 * delivery reports read them, so those reports don't depend on the activity
 * log being kept. `from_status` is null when the task was created in `to_status`.
 */
class TaskStatusChange extends Model
{
    public $timestamps = false;

    protected $fillable = ['task_id', 'from_status', 'to_status', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}

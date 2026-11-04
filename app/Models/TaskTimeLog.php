<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Duration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hours one person spent on a task on one day. See TaskTimeLogPolicy for who
 * may see and change entries.
 */
class TaskTimeLog extends Model
{
    use RecordsActivity;

    protected $fillable = ['task_id', 'user_id', 'logged_on', 'minutes', 'note'];

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'minutes' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function activityTitle(): string
    {
        return Duration::format($this->minutes).' on '.($this->task?->title ?? 'a task');
    }

    public function activityReleaseId(): ?int
    {
        return $this->task?->release_id;
    }

    protected function activityDescription(string $event): string
    {
        return sprintf('%s time entry “%s”', ucfirst($event), $this->activityTitle());
    }
}

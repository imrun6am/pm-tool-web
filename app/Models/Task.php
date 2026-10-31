<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'release_id', 'parent_id', 'title', 'description', 'status',
        'assignee_id', 'created_by', 'due_date', 'phase', 'position',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    /** @var array<string, string> */
    public const STATUSES = [
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'in_review' => 'In Review',
        'recheck' => 'Recheck',
        'done' => 'Done',
        'archive' => 'Archive',
    ];

    /** @var array<string, string> */
    public const STATUS_COLORS = [
        'todo' => 'gray',
        'in_progress' => 'blue',
        'in_review' => 'amber',
        'recheck' => 'orange',
        'done' => 'emerald',
        'archive' => 'slate',
    ];

    /** Statuses that count as finished work (delivered and/or filed away). */
    public const DONE_STATUSES = ['done', 'archive'];

    protected static function booted(): void
    {
        static::deleting(function (Task $task) {
            // Delete subtasks via Eloquent so their comments + activity are handled,
            // then remove this task's own (polymorphic) comments.
            $task->subtasks()->get()->each->delete();
            $task->comments()->delete();
        });
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest();
    }

    public function isSubtask(): bool
    {
        return $this->parent_id !== null;
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function phaseLabel(): ?string
    {
        return $this->phase ? (Release::PHASES[$this->phase] ?? ucfirst($this->phase)) : null;
    }

    /**
     * @return array{done: int, total: int}
     */
    public function subtaskProgress(): array
    {
        $total = $this->subtasks->count();
        $done = $this->subtasks->whereIn('status', self::DONE_STATUSES)->count();

        return ['done' => $done, 'total' => $total];
    }

    public function activityTitle(): string
    {
        return $this->title;
    }

    public function activityReleaseId(): ?int
    {
        return $this->release_id;
    }

    protected function activityExtraIgnored(): array
    {
        return ['position', 'created_by'];
    }
}

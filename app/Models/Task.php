<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'release_id', 'team_id', 'parent_id', 'title', 'description', 'status',
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

    /**
     * Statuses left out of every task count, total, and progress figure.
     * Archived work is filed away: it is never open or overdue, and it does
     * not count as done either.
     */
    public const UNCOUNTED_STATUSES = ['archive'];

    /** Statuses that do take part in counts — every status but the uncounted ones. */
    public static function countedStatuses(): array
    {
        return array_values(array_diff(array_keys(self::STATUSES), self::UNCOUNTED_STATUSES));
    }

    /** Only tasks that take part in counts and progress (archived excluded). */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::UNCOUNTED_STATUSES);
    }

    protected static function booted(): void
    {
        // A linked task's team is always its release's, however it is saved.
        static::saving(function (Task $task) {
            if ($task->release_id && $task->isDirty('release_id')) {
                $task->team_id = Release::whereKey($task->release_id)->value('team_id');
            }
        });

        static::deleting(function (Task $task) {
            // Delete subtasks via Eloquent so their comments + activity are handled,
            // then remove this task's own (polymorphic) comments.
            $task->subtasks()->get()->each->delete();
            $task->comments()->delete();
        });
    }

    /**
     * Tasks the user may see. Leads and viewers see everything. Developers and
     * QA see their current teams' tasks, tasks assigned to them, and teamless
     * tasks they created; other teams' work stays hidden.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasLimitedAccess()) {
            return $query;
        }

        $teamIds = $user->currentTeamIds();

        return $query->where(fn (Builder $q) => $q
            ->whereIn('team_id', $teamIds)
            ->orWhere('assignee_id', $user->id)
            ->orWhere(fn (Builder $own) => $own->whereNull('team_id')->where('created_by', $user->id)));
    }

    /** The single-record form of scopeVisibleTo(). */
    public function isVisibleTo(User $user): bool
    {
        if (! $user->hasLimitedAccess()) {
            return true;
        }

        return ($this->team_id !== null && $user->currentTeamIds()->contains($this->team_id))
            || $this->assignee_id === $user->id
            || ($this->team_id === null && $this->created_by === $user->id);
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** The owning team: the release's team when linked, else optional. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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
        $counted = $this->subtasks->whereNotIn('status', self::UNCOUNTED_STATUSES);
        $total = $counted->count();
        $done = $counted->where('status', 'done')->count();

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

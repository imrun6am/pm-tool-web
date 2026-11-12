<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Services\TaskAssignees;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    use Prunable, RecordsActivity, SoftDeletes;

    /** How long a deleted task can be restored. */
    public const TRASH_DAYS = 30;

    protected $fillable = [
        'release_id', 'team_id', 'parent_id', 'title', 'description', 'status',
        'assignee_id', 'created_by', 'due_date', 'phase', 'position', 'priority', 'estimate_minutes', 'blocked_at', 'blocked_reason',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'estimate_minutes' => 'integer',
            'blocked_at' => 'datetime',
            'trashed_with_parent' => 'boolean',
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

    /** Most urgent first. A task with no priority has none of these. @var array<string, string> */
    public const PRIORITIES = [
        'urgent' => 'Urgent',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    /** @var array<string, string> */
    public const PRIORITY_COLORS = [
        'urgent' => 'rose',
        'high' => 'amber',
        'medium' => 'blue',
        'low' => 'slate',
    ];

    /** The most people one task can be assigned to. */
    public const MAX_ASSIGNEES = 10;

    /**
     * Set by TaskAssignees while it saves the lead, so the saved hook below
     * doesn't treat that write as a direct one.
     */
    public bool $syncingAssignees = false;

    /** What comes before the id in a task's short reference, such as PM-123. */
    public const KEY_PREFIX = 'PM';

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

            // Finished work isn't blocked any more.
            if ($task->isDirty('status') && in_array($task->status, self::DONE_STATUSES, true)) {
                $task->blocked_at = null;
                $task->blocked_reason = null;
            }
        });

        // A direct write of assignee_id (bulk edit, quick add, triage, a
        // factory) means "only this person", as it always did; the assignee
        // list follows, and TaskAssignees sends the notices.
        static::created(function (Task $task) {
            if ($task->assignee_id !== null && ! $task->syncingAssignees) {
                app(TaskAssignees::class)->sync($task, [(int) $task->assignee_id], direct: true);
            }
        });
        static::updated(function (Task $task) {
            if ($task->wasChanged('assignee_id') && ! $task->syncingAssignees) {
                app(TaskAssignees::class)->sync($task, $task->assignee_id ? [(int) $task->assignee_id] : [], direct: true);
            }
        });

        // Keep every status the task passes through, however it is saved.
        static::created(fn (Task $task) => $task->recordStatusChange(null));
        static::updated(function (Task $task) {
            if ($task->wasChanged('status')) {
                $task->recordStatusChange($task->getOriginal('status'));
            }
        });

        // Deleting moves a task to the trash, with its subtasks; its comments,
        // time and history wait with it until it's purged.
        static::deleting(function (Task $task) {
            if ($task->isForceDeleting()) {
                $task->subtasks()->withTrashed()->get()->each->forceDelete();
                $task->comments()->delete();

                return;
            }

            $task->forceFill(['deleted_by' => Auth::id()])->saveQuietly();
            $task->subtasks()->get()->each(function (Task $subtask) {
                $subtask->forceFill(['trashed_with_parent' => true])->saveQuietly();
                $subtask->delete();
            });
        });

        static::restored(function (Task $task) {
            $task->forceFill([
                'deleted_by' => null,
                'trashed_with_parent' => false,
                // The release may have moved to another team in the meantime.
                'team_id' => $task->release_id ? Release::whereKey($task->release_id)->value('team_id') : $task->team_id,
            ])->saveQuietly();

            $task->subtasks()->onlyTrashed()->where('trashed_with_parent', true)->get()->each->restore();
            $task->recordActivity('restored');
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
            ->orWhere(fn (Builder $mine) => $mine->assignedTo($user))
            ->orWhere(fn (Builder $own) => $own->whereNull('team_id')->where('created_by', $user->id)));
    }

    /** The single-record form of scopeVisibleTo(). */
    public function isVisibleTo(User $user): bool
    {
        if (! $user->hasLimitedAccess()) {
            return true;
        }

        return ($this->team_id !== null && $user->currentTeamIds()->contains($this->team_id))
            || $this->hasAssignee($user)
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

    /** The lead assignee: always one of assignees(), and null only when there are none. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    /**
     * Everyone the task is assigned to, the lead included, in the order they
     * were added. Change it through TaskAssignees, never directly.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTrashed()->withTimestamps()
            ->orderBy('task_user.id');
    }

    /**
     * Tasks the user is one of the assignees of, lead or not. The column is
     * qualified, so it also works inside a subtasks whereHas (a self-join).
     */
    public function scopeAssignedTo(Builder $query, User|int $user): Builder
    {
        $id = $user instanceof User ? $user->id : $user;

        return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('task_user')
            ->whereColumn('task_user.task_id', $query->qualifyColumn('id'))
            ->where('task_user.user_id', $id));
    }

    /** Tasks assigned to any of these people. */
    public function scopeAssignedToAny(Builder $query, iterable $userIds): Builder
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->all();

        return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('task_user')
            ->whereColumn('task_user.task_id', $query->qualifyColumn('id'))
            ->whereIn('task_user.user_id', $ids));
    }

    public function hasAssignee(User|int $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->relationLoaded('assignees')
            ? $this->assignees->contains('id', $id)
            : $this->assignees()->whereKey($id)->exists();
    }

    /** @return list<int> every assignee's id, the lead first */
    public function assigneeIds(): array
    {
        return $this->assigneeList()->pluck('id')->all();
    }

    /**
     * Who to show: the lead first, then the others by name. Lists eager-load
     * `assignees` to avoid a query per task.
     *
     * @return Collection<int, User>
     */
    public function assigneeList(): Collection
    {
        return $this->assignees
            ->sortBy(fn (User $u) => [$u->id === $this->assignee_id ? 0 : 1, mb_strtolower($u->name)])
            ->values();
    }

    /** The task's estimate split evenly between its assignees, in minutes. */
    public function estimateShare(): ?float
    {
        if ($this->estimate_minutes === null) {
            return null;
        }

        return $this->estimate_minutes / max(1, $this->relationLoaded('assignees') ? $this->assignees->count() : $this->assignees()->count());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest();
    }

    /** Tasks this one is waiting for. */
    public function blockers(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_id')->withTimestamps();
    }

    /** Tasks waiting for this one. */
    public function blocking(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_id', 'task_id')->withTimestamps();
    }

    public function openBlockers(): BelongsToMany
    {
        return $this->blockers()->whereNotIn('tasks.status', self::DONE_STATUSES);
    }

    /**
     * Flagged as blocked, or waiting for an unfinished task; finished work never
     * is. Lists load `has_open_blockers` (withExists) to avoid a query per task.
     */
    public function isBlocked(): bool
    {
        if (in_array($this->status, self::DONE_STATUSES, true)) {
            return false;
        }

        return $this->blocked_at !== null
            || (bool) ($this->attributes['has_open_blockers'] ?? $this->openBlockers()->exists());
    }

    /** Only tasks that are blocked: flagged, or waiting for an unfinished task. */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::DONE_STATUSES)
            ->where(fn (Builder $q) => $q->whereNotNull('blocked_at')->orWhereHas('openBlockers'));
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class)->orderBy('name');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(TaskStatusChange::class)->orderBy('changed_at')->orderBy('id');
    }

    private function recordStatusChange(?string $from): void
    {
        $this->statusChanges()->create([
            'from_status' => $from,
            // A status left out on create is the column default, which never sat on the model.
            'to_status' => $this->status ?? 'todo',
            'changed_by' => Auth::id(),
            'changed_at' => now(),
        ]);
    }

    public function isSubtask(): bool
    {
        return $this->parent_id !== null;
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    /** The task id in "PM-123" or "123", or null if it isn't one. */
    public static function idFromReference(string $reference): ?int
    {
        return preg_match('/^(?:'.self::KEY_PREFIX.'-?)?(\d+)$/i', trim($reference), $m) ? (int) $m[1] : null;
    }

    /** Whole days since the task last changed status. Lists load the time with withMax. */
    public function daysInStatus(): int
    {
        $since = $this->attributes['status_changes_max_changed_at'] ?? $this->statusChanges()->max('changed_at') ?? $this->updated_at;

        return (int) Carbon::parse($since)->startOfDay()->diffInDays(today());
    }

    /** The short reference people quote, such as PM-123. */
    public function reference(): string
    {
        return self::KEY_PREFIX.'-'.$this->id;
    }

    public function priorityLabel(): ?string
    {
        return $this->priority ? (self::PRIORITIES[$this->priority] ?? ucfirst($this->priority)) : null;
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

    /** Trashed tasks past their restore period; `model:prune` removes them for good. */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::TRASH_DAYS));
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
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
        return ['position', 'created_by', 'deleted_at', 'deleted_by', 'trashed_with_parent'];
    }
}

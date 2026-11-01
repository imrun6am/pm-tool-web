<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Release extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'project_id', 'team_id', 'name', 'description', 'year', 'quarter', 'start_date', 'end_date',
        'completed_at', 'completed_by', 'completion_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
            'year' => 'integer',
            'quarter' => 'integer',
        ];
    }

    /**
     * Canonical, ordered phases every release has.
     *
     * @var array<string, string>
     */
    public const PHASES = [
        'development' => 'Development',
        'qa' => 'QA',
        'retest' => 'Retest',
        'release' => 'Release',
    ];

    /**
     * Fixed colors for phase segments on the timeline.
     *
     * @var array<string, string>
     */
    public const PHASE_COLORS = [
        'development' => '#6366f1', // indigo
        'qa' => '#f59e0b',          // amber
        'retest' => '#f97316',      // orange
        'release' => '#10b981',     // emerald
    ];

    protected static function booted(): void
    {
        // A linked task's team is its release's, so it moves with the release.
        static::updated(function (Release $release) {
            if ($release->wasChanged('team_id')) {
                $release->tasks()->toBase()->update(['team_id' => $release->team_id]);
            }
        });

        static::deleting(function (Release $release) {
            // Stored files must be cleaned explicitly (DB cascade bypasses Eloquent).
            foreach ($release->documents as $document) {
                Storage::disk('local')->delete($document->path);
            }

            // Tasks outlive their release: unlink them (the FK's nullOnDelete
            // does the same) so they stay on the board. Then remove the
            // release's own comments.
            $release->tasks()->toBase()->update(['release_id' => null]);
            $release->comments()->delete();
        });
    }

    /**
     * Releases the user may see: all of them for leads and viewers; only their
     * current teams' releases for developers and QA — plus `$keep`, a record's
     * current release, so an edit form never drops it.
     */
    public function scopeVisibleTo(Builder $query, User $user, ?int $keep = null): Builder
    {
        return $user->hasLimitedAccess()
            ? $query->where(fn (Builder $q) => $q->whereIn('team_id', $user->currentTeamIds())->orWhereKey($keep))
            : $query;
    }

    /** The single-record form of scopeVisibleTo(). */
    public function isVisibleTo(User $user): bool
    {
        return ! $user->hasLimitedAccess() || $user->currentTeamIds()->contains($this->team_id);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** People assigned to this release (a subset of the owning team's members). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /** Where the release stands in time: completed, upcoming, active, or overdue (window over, not completed). */
    public const STATES = [
        'active' => 'In progress',
        'upcoming' => 'Upcoming',
        'overdue' => 'Past window',
        'completed' => 'Completed',
    ];

    public function state(): string
    {
        return match (true) {
            $this->isComplete() => 'completed',
            today()->lt($this->start_date) => 'upcoming',
            today()->gt($this->end_date) => 'overdue',
            default => 'active',
        };
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->state()];
    }

    /** How far through its window the release is today, 0–100. */
    public function elapsedPercent(): int
    {
        if ($this->isComplete() || today()->gt($this->end_date)) {
            return 100;
        }
        if (today()->lt($this->start_date)) {
            return 0;
        }

        return (int) round($this->start_date->diffInDays(today()) / max($this->durationInDays(), 1) * 100);
    }

    /**
     * Adds `tasks_total` and `tasks_done` counts. Archived tasks never take
     * part (Task::scopeCounted); subtasks do, as in the project roll-ups.
     */
    public function scopeWithTaskProgress(Builder $query): Builder
    {
        return $query->withCount([
            'tasks as tasks_total' => fn (Builder $q) => $q->counted(),
            'tasks as tasks_done' => fn (Builder $q) => $q->counted()->where('status', 'done'),
        ]);
    }

    /** @return array{done: int, total: int} — needs scopeWithTaskProgress() */
    public function taskProgress(): array
    {
        return ['done' => (int) ($this->tasks_done ?? 0), 'total' => (int) ($this->tasks_total ?? 0)];
    }

    public function scopeOngoing(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /** Completion notes rendered from Markdown to safe HTML. */
    public function renderedCompletionNotes(): ?HtmlString
    {
        if (blank($this->completion_notes)) {
            return null;
        }

        return new HtmlString(Str::markdown($this->completion_notes, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ReleasePhase::class)->orderBy('position');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ReleaseDocument::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Top-level tasks (not subtasks). */
    public function rootTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id')->orderBy('position')->orderBy('id');
    }

    public function offDays(): HasMany
    {
        return $this->hasMany(ReleaseOffDay::class)->orderBy('date');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest();
    }

    /** Meeting notes linked to this release, newest meeting first. */
    public function meetingNotes(): HasMany
    {
        return $this->hasMany(MeetingNote::class)->orderByDesc('meeting_date')->orderByDesc('id');
    }

    /** Quick links attached to this release, newest first. */
    public function quickLinks(): HasMany
    {
        return $this->hasMany(QuickLink::class)->orderByDesc('id');
    }

    public function quarterLabel(): string
    {
        return 'Q'.$this->quarter;
    }

    public function durationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function offDayCount(): int
    {
        return $this->offDays()->count();
    }

    public function workingDays(): int
    {
        return max($this->durationInDays() - $this->offDayCount(), 0);
    }

    public function activityTitle(): string
    {
        return $this->name;
    }

    public function activityReleaseId(): ?int
    {
        return $this->getKey();
    }
}

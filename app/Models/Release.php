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
        static::deleting(function (Release $release) {
            // Stored files must be cleaned explicitly (DB cascade bypasses Eloquent).
            foreach ($release->documents as $document) {
                Storage::disk('local')->delete($document->path);
            }

            // Delete root tasks via Eloquent so their (and subtasks') polymorphic
            // comments are cleaned up; then remove the release's own comments.
            $release->rootTasks()->get()->each->delete();
            $release->comments()->delete();
        });
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

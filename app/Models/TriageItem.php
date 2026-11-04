<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bug report or request waiting for a lead to decide on it. It isn't a task
 * until accepted, so it stays out of every board, count and report until then.
 */
class TriageItem extends Model
{
    use RecordsActivity;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    protected $fillable = ['title', 'description', 'reporter_id', 'team_id', 'label_id', 'priority', 'triage_import_id'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => self::PENDING];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'team_id' => 'integer', 'reporter_id' => 'integer'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withTrashed();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    /** The import this request came from, whose file stays open to the reporter and whoever may decide it. */
    public function sourceImport(): BelongsTo
    {
        return $this->belongsTo(TriageImport::class, 'triage_import_id');
    }

    /** Waiting items a lead may decide on: every team for org-level leads, their own or unassigned otherwise. */
    public function scopeDecidableBy(Builder $query, User $user): Builder
    {
        if (! $user->isLead()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->when(! $user->canOverseeAllTeams(), fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->whereNull('team_id')
            ->orWhereIn('team_id', Team::where('team_lead_id', $user->id)->select('id'))));
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** Waiting longer than the team aims to respond within. */
    public function isWaitingTooLong(): bool
    {
        return $this->isPending() && $this->created_at->lt(now()->subHours(config('planning.triage_response_hours')));
    }

    public function activityTitle(): string
    {
        return $this->title;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An admin-requested KPI review export (Excel or PDF) for a date range. The
 * work runs as a queued batch, one job per member; this row tracks progress
 * and points at the finished file on the local disk.
 *
 * Like PerformanceScore, this is sensitive HR data and deliberately does NOT
 * use RecordsActivity: the shared activity feed is readable by every user.
 */
class KpiReport extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const SCOPE_ALL = 'all';

    public const SCOPE_TEAM = 'team';

    public const SCOPE_MEMBER = 'member';

    /** @var array<string, string> */
    public const SCOPES = [
        self::SCOPE_ALL => 'All teams',
        self::SCOPE_TEAM => 'One team',
        self::SCOPE_MEMBER => 'One member',
    ];

    /** @var array<string, string> */
    public const FORMATS = [
        'xlsx' => 'Excel',
        'pdf' => 'PDF',
    ];

    protected $fillable = [
        'requested_by', 'scope', 'team_id', 'member_id', 'period_start', 'period_end', 'format', 'status',
        'members_total', 'members_done', 'members_failed', 'batch_id', 'file_path', 'error', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'members_total' => 'integer',
            'members_done' => 'integer',
            'members_failed' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /** The team a team-scoped report covers. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** The member an individual report covers (not the requester). */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id')->withTrashed();
    }

    public function members(): HasMany
    {
        return $this->hasMany(KpiReportMember::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function formatLabel(): string
    {
        return self::FORMATS[$this->format] ?? strtoupper($this->format);
    }

    /** Members analysed so far (done or failed), as a 0–100 percentage. */
    public function progressPercent(): int
    {
        if ($this->members_total === 0) {
            return 0;
        }

        return (int) floor(($this->members_done + $this->members_failed) / $this->members_total * 100);
    }

    /** Only this team's tasksheet rows count; null for all-team and individual reports. */
    public function teamFilter(): ?int
    {
        return $this->scope === self::SCOPE_TEAM ? $this->team_id : null;
    }

    public function coverageLabel(): string
    {
        return match ($this->scope) {
            self::SCOPE_TEAM => 'Team: '.($this->team?->name ?? 'deleted team'),
            self::SCOPE_MEMBER => 'Member: '.($this->subject?->name ?? 'deleted member'),
            default => 'All teams',
        };
    }

    public function filename(): string
    {
        $who = match ($this->scope) {
            self::SCOPE_TEAM => Str::slug($this->team?->name ?? 'team'),
            self::SCOPE_MEMBER => Str::slug($this->subject?->name ?? 'member'),
            default => 'all-teams',
        };

        return sprintf('kpi-review_%s_%s_%s.%s', $who, $this->period_start->toDateString(), $this->period_end->toDateString(), $this->format);
    }
}

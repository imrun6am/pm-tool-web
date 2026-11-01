<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A user-requested tasksheet PDF, generated in the background. The work runs
 * as a queued batch, one job per chunk of days; this row tracks progress and
 * points at the finished file on the private `local` disk.
 *
 * Private to its requester (see TasksheetExportPolicy). Like KpiReport, it
 * does NOT use RecordsActivity: the activity feed is readable by everyone,
 * and an export may carry lead-only feedback.
 */
class TasksheetExport extends Model
{
    use Prunable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_PENDING => 'Queued',
        self::STATUS_PROCESSING => 'Generating',
        self::STATUS_READY => 'Ready',
        self::STATUS_FAILED => 'Failed',
    ];

    /** Where every file of one export lives; deleting it removes all of them. */
    public const DIRECTORY = 'tasksheet-exports';

    protected $fillable = [
        'requested_by', 'team_id', 'member_id', 'date_from', 'date_to', 'filters', 'include_feedback',
        'status', 'chunks_total', 'chunks_done', 'rows_total', 'batch_id', 'file_path', 'error',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'filters' => 'array',
            'include_feedback' => 'boolean',
            'chunks_total' => 'integer',
            'chunks_done' => 'integer',
            'rows_total' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** The member a single-member export covers (not the requester). */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id')->withTrashed();
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Rendered chunks as a 0–100 percentage. The merge counts as one more step,
     * so the bar only reaches 100 once the file actually exists.
     */
    public function progressPercent(): int
    {
        if ($this->isReady()) {
            return 100;
        }

        if ($this->chunks_total === 0) {
            return 0;
        }

        return (int) floor(min($this->chunks_done, $this->chunks_total) / ($this->chunks_total + 1) * 100);
    }

    /** Every chunk is rendered and only the merge remains. */
    public function isMerging(): bool
    {
        return $this->status === self::STATUS_PROCESSING
            && $this->chunks_total > 0
            && $this->chunks_done >= $this->chunks_total;
    }

    /** attendance / fill / leave, in the shape TasksheetService::parseFilters() returns. */
    public function rowFilters(): array
    {
        return ($this->filters ?? []) + ['attendance' => null, 'fill' => null, 'leave' => null, 'member' => null];
    }

    /**
     * Human labels for a set of row filters — the same wording the tasksheet's
     * own filter bar uses.
     *
     * @param  array<string, ?string>  $filters
     * @return list<string>
     */
    public static function filterLabels(array $filters): array
    {
        $labels = [
            'attendance' => ['attended' => 'Attended standup', 'missed' => 'Did not attend standup', 'unmarked' => 'Standup not yet marked'],
            'fill' => ['complete' => 'Complete rows', 'partial' => 'Partially filled rows', 'empty' => 'Empty rows'],
            'leave' => ['working' => 'Working', 'any' => 'On leave'] + TasksheetEntry::LEAVE_TYPES,
        ];

        return collect($labels)
            ->map(fn (array $options, string $key) => $options[$filters[$key] ?? ''] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function directory(): string
    {
        return self::DIRECTORY.'/'.$this->id;
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('tasksheet.export.retention_days', 14)));
    }

    /** Delete the files along with the row. */
    protected function pruning(): void
    {
        Storage::disk('local')->deleteDirectory($this->directory());
    }
}

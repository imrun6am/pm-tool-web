<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's slice of a KPI report: Claude's per-day task counts and the
 * monthly / whole-period review drafts, written by a single queued job.
 */
class KpiReportMember extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'kpi_report_id', 'user_id', 'status', 'analysis', 'error', 'input_tokens', 'output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'analysis' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(KpiReport::class, 'kpi_report_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}

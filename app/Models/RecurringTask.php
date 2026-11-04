<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Work that comes round again. Each run creates an ordinary task. */
class RecurringTask extends Model
{
    use RecordsActivity;

    /** @var array<string, string> */
    public const FREQUENCIES = [
        'daily' => 'Every working day',
        'weekly' => 'Every week',
        'monthly' => 'Every month',
    ];

    protected $fillable = [
        'title', 'description', 'team_id', 'assignee_id', 'priority', 'estimate_minutes',
        'frequency', 'anchor_day', 'next_run_on', 'due_in_days', 'is_active', 'created_by', 'last_run_on',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true, 'due_in_days' => 0];

    protected function casts(): array
    {
        return [
            'next_run_on' => 'date',
            'last_run_on' => 'date',
            'is_active' => 'boolean',
            'estimate_minutes' => 'integer',
            'due_in_days' => 'integer',
            'anchor_day' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function activityTitle(): string
    {
        return $this->title;
    }

    protected function activityExtraIgnored(): array
    {
        return ['next_run_on', 'last_run_on', 'anchor_day', 'created_by'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lead's 1–5 rating of a member on a single competency for one period
 * (a date for daily competencies, a Monday–Sunday week for weekly ones).
 *
 * Performance scores are sensitive HR data: this model deliberately does NOT
 * use RecordsActivity, because the shared activity feed is readable by every
 * authenticated user. The row's own evaluator_id + timestamps are the audit
 * trail. Notes are lead-only and must only ever render behind an isLead() gate.
 */
class PerformanceScore extends Model
{
    protected $fillable = [
        'team_id', 'user_id', 'evaluator_id', 'competency_id',
        'period_type', 'period_start', 'period_end', 'score', 'note',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'score' => 'integer',
        ];
    }

    /** The 1–5 scale with fixed anchor labels. */
    public const SCALE = [
        1 => 'Needs Improvement',
        2 => 'Below Expectations',
        3 => 'Meets Expectations',
        4 => 'Exceeds Expectations',
        5 => 'Outstanding',
    ];

    public const MIN_SCORE = 1;

    public const MAX_SCORE = 5;

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(PerformanceCompetency::class, 'competency_id');
    }

    public function scoreLabel(): string
    {
        return self::SCALE[$this->score] ?? (string) $this->score;
    }

    /** Anchor label for any 1–5 value (used by forms and analytics). */
    public static function label(int $score): string
    {
        return self::SCALE[$score] ?? (string) $score;
    }
}

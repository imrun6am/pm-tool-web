<?php

namespace App\Http\Resources\V1;

use App\Models\PerformanceScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One lead's rating of a member on a competency for one period.
 *
 * Every route that reaches this resource is already behind the `lead`
 * middleware and team scoping, so the private `note` is safe to include here —
 * a non-lead never gets a response containing a score at all.
 *
 * @mixin PerformanceScore
 */
class PerformanceScoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'user_id' => $this->user_id,
            'competency_id' => $this->competency_id,
            'score' => $this->score,
            'score_label' => $this->scoreLabel(),
            'note' => $this->note,
            'period_type' => $this->period_type,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'member' => new UserSummaryResource($this->whenLoaded('member')),
            'evaluator' => new UserSummaryResource($this->whenLoaded('evaluator')),
            'competency' => new PerformanceCompetencyResource($this->whenLoaded('competency')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

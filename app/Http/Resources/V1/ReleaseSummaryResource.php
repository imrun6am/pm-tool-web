<?php

namespace App\Http\Resources\V1;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact form of a release — what a picker, a task card, or a linked
 * record needs, without the phases, members, or counts.
 *
 * @mixin Release
 */
class ReleaseSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'year' => $this->year,
            'quarter' => $this->quarter,
            'quarter_label' => $this->quarterLabel(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_complete' => $this->isComplete(),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'team' => new TeamSummaryResource($this->whenLoaded('team')),
        ];
    }
}

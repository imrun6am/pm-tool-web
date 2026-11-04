<?php

namespace App\Http\Resources\V1;

use App\Models\TriageItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TriageItem */
class TriageItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'team_id' => $this->team_id,
            'label_id' => $this->label_id,
            'task_id' => $this->task_id,
            'decline_reason' => $this->decline_reason,
            'reporter' => new UserSummaryResource($this->whenLoaded('reporter')),
            'is_waiting_too_long' => $this->created_at && $this->isWaitingTooLong(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

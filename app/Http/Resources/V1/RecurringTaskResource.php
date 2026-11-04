<?php

namespace App\Http\Resources\V1;

use App\Models\RecurringTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringTask */
class RecurringTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'team_id' => $this->team_id,
            'assignee_id' => $this->assignee_id,
            'priority' => $this->priority,
            'estimate_minutes' => $this->estimate_minutes,
            'frequency' => $this->frequency,
            'next_run_on' => $this->next_run_on?->toDateString(),
            'last_run_on' => $this->last_run_on?->toDateString(),
            'due_in_days' => $this->due_in_days,
            'is_active' => $this->is_active,
        ];
    }
}

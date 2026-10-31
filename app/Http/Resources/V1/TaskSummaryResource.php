<?php

namespace App\Http\Resources\V1;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact form of a task — a subtask row, or a parent reference.
 *
 * @mixin Task
 */
class TaskSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'status_color' => $this->statusColor(),
            'is_subtask' => $this->isSubtask(),
            'parent_id' => $this->parent_id,
            'release_id' => $this->release_id,
            'due_date' => $this->due_date?->toDateString(),
            'position' => $this->position,
            'assignee' => new UserSummaryResource($this->whenLoaded('assignee')),
        ];
    }
}

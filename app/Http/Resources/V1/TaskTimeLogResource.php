<?php

namespace App\Http\Resources\V1;

use App\Models\TaskTimeLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskTimeLog */
class TaskTimeLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'logged_on' => $this->logged_on->toDateString(),
            'minutes' => $this->minutes,
            'note' => $this->note,
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\V1;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A task or subtask in full.
 *
 * `subtask_progress` is only meaningful once the subtasks are loaded — the
 * model counts them off the loaded relation — so it is emitted only then,
 * rather than silently reporting 0/0 for an unloaded relation.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'release_id' => $this->release_id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'status_color' => $this->statusColor(),
            'is_subtask' => $this->isSubtask(),
            'is_done' => $this->isDone(),
            'due_date' => $this->due_date?->toDateString(),
            'phase' => $this->phase,
            'phase_label' => $this->phaseLabel(),
            'position' => $this->position,

            'assignee_id' => $this->assignee_id,
            'assignee' => new UserSummaryResource($this->whenLoaded('assignee')),
            'created_by' => new UserSummaryResource($this->whenLoaded('creator')),
            'release' => new ReleaseSummaryResource($this->whenLoaded('release')),
            'parent' => new TaskSummaryResource($this->whenLoaded('parent')),
            'subtasks' => TaskSummaryResource::collection($this->whenLoaded('subtasks')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),

            'subtask_progress' => $this->when(
                $this->relationLoaded('subtasks'),
                fn () => $this->subtaskProgress()
            ),
            'comments_count' => $this->whenCounted('comments'),
            'subtasks_count' => $this->whenCounted('subtasks'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\V1;

use App\Models\Comment;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment on a release or a task.
 *
 * The polymorphic parent is published as a short slug (`release` / `task`)
 * rather than the fully-qualified model class, so the client is not coupled to
 * the server's PHP namespace and a class move cannot break it.
 *
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'commentable_type' => match ($this->commentable_type) {
                Release::class => 'release',
                Task::class => 'task',
                default => null,
            },
            'commentable_id' => (int) $this->commentable_id,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

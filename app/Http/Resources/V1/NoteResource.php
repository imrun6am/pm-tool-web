<?php

namespace App\Http\Resources\V1;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A daily note. The body is the sanitized HTML the model stores on write, so a
 * client may render it directly; it has already been through HtmlSanitizer.
 *
 * @mixin Note
 */
class NoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'body' => $this->body,
            'visibility' => $this->visibility,
            'visibility_label' => $this->visibilityLabel(),
            'visibility_badge' => $this->visibilityBadge(),
            'author' => new UserSummaryResource($this->whenLoaded('author')),
            'recipients' => UserSummaryResource::collection($this->whenLoaded('recipients')),
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

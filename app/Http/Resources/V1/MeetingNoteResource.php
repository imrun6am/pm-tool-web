<?php

namespace App\Http\Resources\V1;

use App\Models\MeetingNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minutes of a meeting. A record only reaches this resource once the query has
 * already scoped it with `visibleTo()`, so an attendees-only note never gets
 * here for someone who may not read it.
 *
 * @mixin MeetingNote
 */
class MeetingNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'type_color' => $this->typeColor(),
            'meeting_date' => $this->meeting_date?->toDateString(),
            'body' => $this->body,
            'visibility' => $this->visibility,
            'visibility_label' => $this->visibilityLabel(),
            'is_attendees_only' => $this->isAttendeesOnly(),
            'release_id' => $this->release_id,
            'release' => new ReleaseSummaryResource($this->whenLoaded('release')),
            'event_id' => $this->event_id,
            'event' => new EventResource($this->whenLoaded('event')),
            'author' => new UserSummaryResource($this->whenLoaded('author')),
            'attendees' => UserSummaryResource::collection($this->whenLoaded('attendees')),
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

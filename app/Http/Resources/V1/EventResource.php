<?php

namespace App\Http\Resources\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A calendar event.
 *
 * `covered_dates` is the list of Y-m-d days the event spans. A client laying
 * out a month grid needs to know which cells a multi-day event occupies, and
 * deriving that from start/end means re-implementing the clipping the server
 * already does.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'type_color' => $this->typeColor(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'all_day' => (bool) $this->all_day,
            'is_multi_day' => $this->isMultiDay(),
            'time_label' => $this->timeLabel(),
            'covered_dates' => $this->coveredDates(),
            'location' => $this->location,
            'release_id' => $this->release_id,
            'release' => new ReleaseSummaryResource($this->whenLoaded('release')),
            'created_by' => new UserSummaryResource($this->whenLoaded('creator')),
            'attendees' => UserSummaryResource::collection($this->whenLoaded('attendees')),
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

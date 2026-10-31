<?php

namespace App\Http\Resources\V1;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One entry in the app-wide audit feed.
 *
 * `changes` is the old → new diff the model derives from its stored
 * properties; it is present only for `updated` events, where a diff means
 * something. The subject type is published as a short slug (`release`, `task`)
 * rather than the model's FQCN so clients are not coupled to PHP namespaces.
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'event_color' => $this->eventColor(),
            'description' => $this->description,
            'log_name' => $this->log_name,
            'subject_type' => $this->subject_type ? Str::snake(class_basename($this->subject_type)) : null,
            'subject_id' => $this->subject_id,
            'release_id' => $this->release_id,
            'causer' => new UserSummaryResource($this->whenLoaded('causer')),
            'changes' => $this->when($this->event === 'updated', fn () => $this->changes()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

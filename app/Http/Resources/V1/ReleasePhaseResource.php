<?php

namespace App\Http\Resources\V1;

use App\Models\Release;
use App\Models\ReleasePhase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the four canonical phases of a release. The color travels with the
 * phase so every client draws the timeline in the same palette as the web app
 * without maintaining its own copy of the mapping.
 *
 * @mixin ReleasePhase
 */
class ReleasePhaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'release_id' => $this->release_id,
            'phase' => $this->phase,
            'label' => $this->label(),
            'color' => Release::PHASE_COLORS[$this->phase] ?? null,
            'position' => $this->position,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
        ];
    }
}

<?php

namespace App\Http\Resources\V1;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_archived' => $this->isArchived(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'team_lead_id' => $this->team_lead_id,
            'team_lead' => new UserSummaryResource($this->whenLoaded('teamLead')),
            'members' => UserSummaryResource::collection($this->whenLoaded('members')),
            'member_count' => $this->whenCounted('members'),
            'release_count' => $this->whenCounted('releases'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

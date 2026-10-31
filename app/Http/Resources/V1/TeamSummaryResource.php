<?php

namespace App\Http\Resources\V1;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact form of a team — enough to label and color a bar, a chip, or a
 * picker entry without loading its members.
 *
 * @mixin Team
 */
class TeamSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'is_archived' => $this->isArchived(),
        ];
    }
}

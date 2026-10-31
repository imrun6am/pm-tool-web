<?php

namespace App\Http\Resources\V1;

use App\Models\ReleaseOffDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReleaseOffDay
 */
class ReleaseOffDayResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'release_id' => $this->release_id,
            'date' => $this->date?->toDateString(),
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

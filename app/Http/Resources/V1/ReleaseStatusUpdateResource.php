<?php

namespace App\Http\Resources\V1;

use App\Models\ReleaseStatusUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReleaseStatusUpdate */
class ReleaseStatusUpdateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'release_id' => $this->release_id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'note' => $this->note,
            'author' => new UserSummaryResource($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

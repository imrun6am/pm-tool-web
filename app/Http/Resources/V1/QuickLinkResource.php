<?php

namespace App\Http\Resources\V1;

use App\Models\QuickLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuickLink
 */
class QuickLinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'url' => $this->url,
            'visibility' => $this->visibility,
            'visibility_label' => QuickLink::VISIBILITIES[$this->visibility] ?? ucfirst((string) $this->visibility),
            'is_shared' => $this->isShared(),
            // The requesting user's own pin — never anyone else's.
            'is_pinned' => $request->user() ? $this->isPinnedBy($request->user()) : false,
            'release_id' => $this->release_id,
            'release' => new ReleaseSummaryResource($this->whenLoaded('release')),
            'author' => new UserSummaryResource($this->whenLoaded('author')),
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

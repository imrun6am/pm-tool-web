<?php

namespace App\Http\Resources\V1;

use App\Models\SavedView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SavedView */
class SavedViewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page' => $this->page,
            'name' => $this->name,
            'filters' => (object) $this->filters,
            'is_shared' => $this->is_shared,
            'is_mine' => $this->user_id === $request->user()?->id,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}

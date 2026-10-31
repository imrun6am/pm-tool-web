<?php

namespace App\Http\Resources\V1;

use App\Models\PerformanceCompetency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PerformanceCompetency
 */
class PerformanceCompetencyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'role_scope' => $this->role_scope,
            'role_scope_label' => $this->roleScopeLabel(),
            'cadence' => $this->cadence,
            'cadence_label' => $this->cadenceLabel(),
            'is_daily' => $this->isDaily(),
            'weight' => $this->weight,
            'active' => (bool) $this->active,
            'position' => $this->position,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

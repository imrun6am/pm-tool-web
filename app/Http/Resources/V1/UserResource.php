<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user account as seen through the user-management endpoints.
 *
 * Credentials are never serialized: the model hides `password` and
 * `remember_token`, and this resource lists its fields explicitly rather than
 * spreading the model, so a column added later cannot leak by default.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_label' => $this->roleLabel(),
            'is_active' => $this->isActive(),
            'is_deleted' => $this->trashed(),
            'status_tag' => $this->statusTag(),
            'is_lead' => $this->isLead(),
            'has_limited_access' => $this->hasLimitedAccess(),
            'deactivated_at' => $this->deactivated_at?->toIso8601String(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'teams' => TeamSummaryResource::collection($this->whenLoaded('teams')),
            'assigned_task_count' => $this->whenCounted('assignedTasks'),
        ];
    }
}

<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact form of a user, used wherever a user appears *inside* another
 * resource — an assignee, an author, an attendee, a team member.
 *
 * `status_tag` matters here: a task's assignee or a comment's author may have
 * been deactivated or deleted since, and the client must be able to say so
 * rather than render a bare name as though the account were live.
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
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
        ];
    }
}

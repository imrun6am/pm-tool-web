<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One signed-in device.
 *
 * The token itself is never serialized: Sanctum stores only a hash, and the
 * plain-text value is shown exactly once, at login. `is_current` lets a client
 * mark "this device" in a device list so a user does not sign themselves out
 * by mistake while revoking another.
 *
 * @mixin PersonalAccessToken
 */
class PersonalAccessTokenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_name' => $this->name,
            'is_current' => $this->id === $request->user()?->currentAccessToken()?->id,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

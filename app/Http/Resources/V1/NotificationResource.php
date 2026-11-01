<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One inbox notification. `kind` is one of NotificationService::KINDS;
 * `message` is the ready-made sentence the web inbox shows.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'kind' => $data['kind'] ?? null,
            'message' => $data['message'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'task' => ['id' => $data['task_id'] ?? null, 'title' => $data['task_title'] ?? null],
            'actor' => isset($data['actor_id']) ? ['id' => $data['actor_id'], 'name' => $data['actor_name'] ?? null] : null,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

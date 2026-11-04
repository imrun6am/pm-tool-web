<?php

namespace App\Http\Resources\V1;

use App\Models\ReleaseTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReleaseTemplate */
class ReleaseTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tasks' => $this->tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'phase' => $task->phase,
                'priority' => $task->priority,
                'estimate_minutes' => $task->estimate_minutes,
            ])->all(),
        ];
    }
}

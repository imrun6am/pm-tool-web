<?php

namespace App\Http\Resources\V1;

use App\Models\DailyStandup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The generated part of a standup meeting note. Rich-text fields are sanitized
 * HTML, and restricted tasksheet fields were never copied into `content`.
 *
 * @mixin DailyStandup
 */
class DailyStandupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'team_id' => $this->team_id,
            'team_name' => $this->team?->name,
            'date' => $this->date?->toDateString(),
            'previous_date' => $this->content['previous_date'] ?? null,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'attendees_edited' => $this->attendees_edited_at !== null,
            'counts' => $this->content['counts'] ?? [],
            'people' => $this->content['people'] ?? [],
            'on_leave' => $this->content['on_leave'] ?? [],
            'not_filled' => $this->content['not_filled'] ?? [],
            'ai' => $this->content['ai'] ?? null,
            'can_refresh' => $this->team && ($request->user()?->can('generate', [DailyStandup::class, $this->team]) ?? false),
        ];
    }
}

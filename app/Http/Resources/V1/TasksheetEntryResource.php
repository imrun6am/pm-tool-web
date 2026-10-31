<?php

namespace App\Http\Resources\V1;

use App\Models\TasksheetEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One member's row on a team's daily tasksheet.
 *
 * `feedback` is the team lead's private note on the member. It is emitted only
 * when the viewer is a lead — omitted entirely, not blanked — so a non-lead
 * client never receives the value in the first place and cannot reveal it by
 * ignoring a flag. This mirrors the Blade side, where the column renders only
 * behind a User::isLead() gate.
 *
 * @mixin TasksheetEntry
 */
class TasksheetEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'user_id' => $this->user_id,
            'date' => $this->date?->toDateString(),

            'plan' => $this->plan,
            'result' => $this->result,
            'comment' => $this->comment,
            'tickets' => $this->tickets,
            'work_points' => $this->work_points,
            'ticket_count' => $this->ticket_count,
            'ticket_points' => $this->ticket_points,

            // Three-valued on purpose: null is "not yet marked", which a client
            // must be able to tell apart from an explicit "did not attend".
            'standup_attended' => $this->standup_attended,
            'is_standup_unmarked' => $this->isStandupUnmarked(),
            'accepts_standup_attendance' => $this->acceptsStandupAttendance(),

            'leave_type' => $this->leave_type,
            'leave_label' => $this->leave_type ? $this->leaveLabel() : null,
            'is_on_leave' => $this->isOnLeave(),
            'is_full_day_leave' => $this->isFullDayLeave(),
            'is_half_day' => $this->isHalfDay(),

            'filled_field_count' => $this->filledFieldCount(),
            'is_fully_filled' => $this->isFullyFilled(),
            'is_partially_filled' => $this->isPartiallyFilled(),
            'was_filled_late' => $this->wasFilledLate(),

            // Lead-only: the member's own client must never receive this.
            'feedback' => $this->when((bool) $viewer?->isLead(), fn () => $this->feedback),

            'member' => new UserSummaryResource($this->whenLoaded('member')),
            'team' => new TeamSummaryResource($this->whenLoaded('team')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

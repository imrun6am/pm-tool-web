<?php

namespace App\Http\Resources\V1;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A release in full.
 *
 * The duration / off-day / working-day figures are computed server-side rather
 * than left to the client: they are the same numbers the web app shows, and
 * recomputing "working days" in every client is how two surfaces end up
 * disagreeing about how long a release actually is.
 *
 * @mixin Release
 */
class ReleaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'project_id' => $this->project_id,
            'team_id' => $this->team_id,
            'year' => $this->year,
            'quarter' => $this->quarter,
            'quarter_label' => $this->quarterLabel(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'duration_days' => $this->durationInDays(),
            'off_day_count' => $this->offDayCount(),
            'working_days' => $this->workingDays(),

            'is_complete' => $this->isComplete(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completion_notes' => $this->completion_notes,
            'completed_by' => new UserSummaryResource($this->whenLoaded('completedBy')),

            'project' => new ProjectResource($this->whenLoaded('project')),
            'team' => new TeamSummaryResource($this->whenLoaded('team')),
            'phases' => ReleasePhaseResource::collection($this->whenLoaded('phases')),
            'members' => UserSummaryResource::collection($this->whenLoaded('members')),
            'off_days' => ReleaseOffDayResource::collection($this->whenLoaded('offDays')),
            'documents' => ReleaseDocumentResource::collection($this->whenLoaded('documents')),
            'tasks' => TaskResource::collection($this->whenLoaded('rootTasks')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'meeting_notes' => MeetingNoteResource::collection($this->whenLoaded('meetingNotes')),
            'quick_links' => QuickLinkResource::collection($this->whenLoaded('quickLinks')),

            'tasks_count' => $this->whenCounted('tasks'),
            'documents_count' => $this->whenCounted('documents'),
            'comments_count' => $this->whenCounted('comments'),
            'off_days_count' => $this->whenCounted('offDays'),
            'members_count' => $this->whenCounted('members'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

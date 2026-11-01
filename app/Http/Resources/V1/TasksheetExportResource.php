<?php

namespace App\Http\Resources\V1;

use App\Models\TasksheetExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A background tasksheet PDF, as a client polls it.
 *
 * The status block carries the same values as the web exports page's status
 * JSON (WebApiParityTest pins them together); only `download_url` differs,
 * pointing at the API's own download route. The selection rides along so a
 * client can label an export without looking its team or member up.
 *
 * @mixin TasksheetExport
 */
class TasksheetExportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'progress' => $this->progressPercent(),
            'chunks_done' => $this->chunks_done,
            'chunks_total' => $this->chunks_total,
            'rows_total' => $this->rows_total,
            'merging' => $this->isMerging(),
            'error' => $this->error,
            'download_url' => $this->isReady() ? route('api-v1.tasksheet.exports.download', $this->resource) : null,

            'team_id' => $this->team_id,
            'member_id' => $this->member_id,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            // An object even when empty, so a client can always index it by key.
            'filters' => (object) ($this->filters ?? []),
            'filter_labels' => TasksheetExport::filterLabels($this->rowFilters()),
            'includes_feedback' => $this->include_feedback,
            'requested_by' => $this->requested_by,

            'team' => new TeamSummaryResource($this->whenLoaded('team')),
            'member' => new UserSummaryResource($this->whenLoaded('subject')),

            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}

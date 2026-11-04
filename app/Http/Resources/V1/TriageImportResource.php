<?php

namespace App\Http\Resources\V1;

use App\Models\TriageImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TriageImport */
class TriageImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'input' => $this->input,
            'name' => $this->displayName(),
            'preview' => $this->preview,
            'extension' => $this->extension,
            'size' => $this->size,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'is_running' => $this->isRunning(),
            'progress' => $this->progressPercent(),
            'proposal_count' => $this->proposalCount(),
            'proposals_dropped' => $this->proposals_dropped,
            'requests_created' => $this->requests_created,
            'error' => $this->error,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

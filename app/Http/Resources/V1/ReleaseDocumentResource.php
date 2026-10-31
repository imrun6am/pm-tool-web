<?php

namespace App\Http\Resources\V1;

use App\Models\ReleaseDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An uploaded release document.
 *
 * The stored `path` is deliberately absent. Documents live on the private disk
 * and are served only through the authenticated download endpoint, so handing
 * a client the storage path would either be useless or — if the disk were ever
 * made public — a way around the authorization the download route performs.
 *
 * @mixin ReleaseDocument
 */
class ReleaseDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'release_id' => $this->release_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'human_size' => $this->humanSize(),
            'download_url' => route('api-v1.releases.documents.download', [
                'release' => $this->release_id,
                'document' => $this->id,
            ]),
            'uploaded_by' => new UserSummaryResource($this->whenLoaded('uploader')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

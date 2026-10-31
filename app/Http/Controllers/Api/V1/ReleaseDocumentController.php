<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ReleaseDocumentRequest;
use App\Http\Resources\V1\ReleaseDocumentResource;
use App\Models\Release;
use App\Models\ReleaseDocument;
use App\Services\ReleaseDocumentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReleaseDocumentController extends ApiController
{
    public function __construct(private readonly ReleaseDocumentService $documents) {}

    public function index(Release $release): JsonResponse
    {
        return $this->ok(ReleaseDocumentResource::collection($this->documents->forRelease($release)));
    }

    /** Upload authorization (contributors, not viewers) lives in the request. */
    public function store(ReleaseDocumentRequest $request, Release $release): JsonResponse
    {
        $document = $this->documents->store($release, $request->file('document'), $request->user());

        return $this->created(
            new ReleaseDocumentResource($document->load('uploader')),
            'Document uploaded.'
        );
    }

    /** The one endpoint that does not return JSON — it streams the file itself. */
    public function download(Release $release, ReleaseDocument $document): StreamedResponse
    {
        return $this->documents->download($release, $document);
    }

    public function destroy(Release $release, ReleaseDocument $document): JsonResponse
    {
        $this->documents->delete($release, $document);

        return $this->message('Document deleted.');
    }
}

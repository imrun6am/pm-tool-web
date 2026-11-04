<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ServesTriageImportFile;
use App\Http\Requests\ConfirmTriageImportRequest;
use App\Http\Requests\TriageImportRequest;
use App\Http\Requests\TriageImportTextRequest;
use App\Http\Resources\V1\TriageImportResource;
use App\Http\Resources\V1\TriageItemResource;
use App\Models\TriageImport;
use App\Models\TriageItem;
use App\Services\TriageImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Creating requests from a file, screenshot or text. An import is private to the person who started it. */
class TriageImportController extends ApiController
{
    use ServesTriageImportFile;

    public function __construct(private readonly TriageImportService $imports) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok([
            'available' => $this->imports->available(),
            'imports' => TriageImportResource::collection($this->imports->history($request->user()))->resolve($request),
        ]);
    }

    public function store(TriageImportRequest $request): JsonResponse
    {
        return $this->created(new TriageImportResource($this->imports->start($request->user(), $request->file('document'))), 'Import started.');
    }

    public function storeText(TriageImportTextRequest $request): JsonResponse
    {
        return $this->created(new TriageImportResource($this->imports->startFromText($request->user(), $request->validated('text'))), 'Import started.');
    }

    public function show(Request $request, TriageImport $triageImport): JsonResponse
    {
        $this->authorize('view', $triageImport);

        return $this->ok([
            'import' => (new TriageImportResource($triageImport))->resolve($request),
            'proposals' => $triageImport->isReviewable() ? $this->imports->reviewRows($triageImport) : [],
        ]);
    }

    public function confirm(ConfirmTriageImportRequest $request, TriageImport $triageImport): JsonResponse
    {
        $this->authorize('confirm', $triageImport);
        abort_unless($triageImport->isReviewable(), 409, 'This import is not ready for review.');

        $created = $this->imports->confirm($triageImport, $request->validated('requests'), $request->user());
        abort_if($created->isEmpty(), 422, 'Select at least one request that hasn’t been sent yet.');

        return $this->ok(
            ['requests' => TriageItemResource::collection($created)->resolve($request)],
            $created->count().' '.Str::plural('request', $created->count()).' sent.',
        );
    }

    public function retry(TriageImport $triageImport): JsonResponse
    {
        $this->authorize('retry', $triageImport);
        abort_unless($triageImport->isFailed(), 409, 'Only a failed import can be tried again.');
        abort_unless($this->imports->available(), 409, 'AI isn’t available right now.');

        $this->imports->retry($triageImport);

        return $this->ok(new TriageImportResource($triageImport->refresh()), 'Import queued again.');
    }

    public function destroy(TriageImport $triageImport): JsonResponse
    {
        $this->authorize('delete', $triageImport);

        $this->imports->delete($triageImport);

        return $this->ok(null, 'Import deleted.');
    }

    public function file(TriageImport $triageImport): StreamedResponse
    {
        $this->authorize('view', $triageImport);

        return $this->importFile($triageImport);
    }

    public function source(TriageItem $triageItem): StreamedResponse
    {
        $this->authorize('viewSource', $triageItem);
        abort_unless($triageItem->sourceImport, 404, 'This request has no original file.');

        return $this->importFile($triageItem->sourceImport);
    }
}

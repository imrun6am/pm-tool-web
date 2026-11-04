<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TriageAcceptRequest;
use App\Http\Requests\TriageDeclineRequest;
use App\Http\Requests\TriageItemRequest;
use App\Http\Resources\V1\TriageItemResource;
use App\Models\TriageItem;
use App\Services\TriageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Incoming requests. `queue` is what the caller may decide on, and is empty for anyone but a lead. */
class TriageController extends ApiController
{
    public function __construct(private readonly TriageService $triage) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok([
            'queue' => TriageItemResource::collection($this->triage->queue($request->user()))->resolve($request),
            'mine' => TriageItemResource::collection($this->triage->mine($request->user()))->resolve($request),
        ]);
    }

    public function store(TriageItemRequest $request): JsonResponse
    {
        return $this->created(new TriageItemResource($this->triage->submit($request->user(), $request->validated())), 'Request sent.');
    }

    public function accept(TriageAcceptRequest $request, TriageItem $triageItem): JsonResponse
    {
        abort_unless($triageItem->isPending(), 409, 'This request has already been decided.');

        return $this->ok(new TriageItemResource($this->triage->accept($triageItem, $request->user(), $request->validated())), 'Request accepted.');
    }

    public function decline(TriageDeclineRequest $request, TriageItem $triageItem): JsonResponse
    {
        abort_unless($triageItem->isPending(), 409, 'This request has already been decided.');

        return $this->ok(new TriageItemResource($this->triage->decline($triageItem, $request->user(), $request->validated('reason'))), 'Request declined.');
    }
}

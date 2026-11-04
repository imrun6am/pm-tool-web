<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LabelRequest;
use App\Http\Resources\V1\LabelResource;
use App\Models\Label;
use App\Services\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Task labels. Anyone can read the list, for pickers; leads change it. */
class LabelController extends ApiController
{
    public function __construct(private readonly LabelService $labels) {}

    public function index(): AnonymousResourceCollection
    {
        return LabelResource::collection($this->labels->all());
    }

    public function store(LabelRequest $request): JsonResponse
    {
        return $this->created(new LabelResource($this->labels->create($request->validated())), 'Label added.');
    }

    public function update(LabelRequest $request, Label $label): JsonResponse
    {
        return $this->ok(new LabelResource($this->labels->update($label, $request->validated())), 'Label updated.');
    }

    public function destroy(Label $label): JsonResponse
    {
        $this->labels->delete($label);

        return $this->message('Label deleted.');
    }
}

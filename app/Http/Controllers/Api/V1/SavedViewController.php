<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\SavedViewRequest;
use App\Http\Resources\V1\SavedViewResource;
use App\Models\SavedView;
use App\Services\SavedViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Saved filter sets for the task list and the board. */
class SavedViewController extends ApiController
{
    public function __construct(private readonly SavedViewService $views) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $page = $request->validate(['page' => ['required', Rule::in(array_keys(SavedView::PAGES))]])['page'];

        return SavedViewResource::collection($this->views->forPage($request->user(), $page));
    }

    public function store(SavedViewRequest $request): JsonResponse
    {
        $view = $this->views->save($request->user(), $request->validated('page'), $request->validated('name'), $request->validated('filters') ?? [], $request->boolean('shared'));

        return $this->created(new SavedViewResource($view), 'View saved.');
    }

    public function destroy(SavedView $view): JsonResponse
    {
        $this->authorize('delete', $view);

        $this->views->delete($view);

        return $this->message('View removed.');
    }
}

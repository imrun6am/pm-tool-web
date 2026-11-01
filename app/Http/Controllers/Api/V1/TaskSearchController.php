<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TaskSummaryResource;
use App\Services\TaskSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Title search over the tasks the caller may see — the ⌘K results, as JSON. */
class TaskSearchController extends ApiController
{
    public function __invoke(Request $request, TaskSearchService $search): AnonymousResourceCollection
    {
        return TaskSummaryResource::collection($search->search($request->user(), (string) $request->query('q', '')));
    }
}

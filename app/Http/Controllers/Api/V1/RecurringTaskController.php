<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RecurringTaskRequest;
use App\Http\Resources\V1\RecurringTaskResource;
use App\Models\RecurringTask;
use App\Services\RecurringTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Recurring task rules. Leads only. */
class RecurringTaskController extends ApiController
{
    public function __construct(private readonly RecurringTaskService $recurring) {}

    public function index(): AnonymousResourceCollection
    {
        return RecurringTaskResource::collection($this->recurring->all());
    }

    public function store(RecurringTaskRequest $request): JsonResponse
    {
        return $this->created(new RecurringTaskResource($this->recurring->create($request->ruleAttributes(), $request->user())), 'Recurring task added.');
    }

    public function update(Request $request, RecurringTask $recurringTask): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        return $this->ok(new RecurringTaskResource($this->recurring->setActive($recurringTask, $data['is_active'])), 'Recurring task updated.');
    }

    public function destroy(RecurringTask $recurringTask): JsonResponse
    {
        $this->recurring->delete($recurringTask);

        return $this->message('Recurring task removed.');
    }
}

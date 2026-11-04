<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\WipLimitRequest;
use App\Services\BoardService;
use Illuminate\Http\JsonResponse;

/** A board column's limit for one team. `max_tasks: null` clears it. */
class WipLimitController extends ApiController
{
    public function __construct(private readonly BoardService $board) {}

    public function update(WipLimitRequest $request): JsonResponse
    {
        $this->board->setWipLimit($request->integer('team_id'), $request->validated('status'), $request->validated('max_tasks'));

        return $this->ok(['limits' => $this->board->wipLimits($request->integer('team_id'))], 'Limit saved.');
    }
}

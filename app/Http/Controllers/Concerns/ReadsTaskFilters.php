<?php

namespace App\Http\Controllers\Concerns;

use App\Services\TaskService;
use Illuminate\Http\Request;

trait ReadsTaskFilters
{
    /**
     * An optional id filter that also accepts TaskService::NONE ("none") for
     * tasks with nothing set — `?release_id=none`, `?team_id=none`.
     */
    protected function idOrNoneFilter(Request $request, string $key): int|string|null
    {
        if ($request->input($key) === TaskService::NONE) {
            return TaskService::NONE;
        }

        return $request->filled($key) ? (int) $request->integer($key) : null;
    }
}

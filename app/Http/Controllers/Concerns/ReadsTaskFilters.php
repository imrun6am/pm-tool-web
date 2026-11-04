<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Task;
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

    /**
     * Everything the task list accepts, shared by web and API.
     *
     * @return array<string, mixed>
     */
    protected function taskListFilters(Request $request): array
    {
        return [
            'include_subtasks' => $request->boolean('include_subtasks'),
            'q' => $request->filled('q') ? trim($request->string('q')->toString()) : null,
            'release_id' => $this->idOrNoneFilter($request, 'release_id'),
            'team_id' => $this->idOrNoneFilter($request, 'team_id'),
            'assignee_id' => $this->idOrNoneFilter($request, 'assignee_id'),
            'parent_id' => $request->filled('parent_id') ? (int) $request->integer('parent_id') : null,
            'status' => $request->input('status'),
            'phase' => $request->input('phase'),
            'due_before' => $request->filled('due_before') ? $request->date('due_before') : null,
            'due_after' => $request->filled('due_after') ? $request->date('due_after') : null,
            'overdue' => $request->boolean('overdue'),
            'sort' => in_array($request->input('sort'), TaskService::SORTS, true) ? $request->input('sort') : null,
            'direction' => $request->input('direction') === 'desc' ? 'desc' : 'asc',
        ] + $this->fieldFilters($request);
    }

    /**
     * The priority, label and blocked filters, shared by every task list.
     * `priority` is a priority or TaskService::NONE; anything else means no filter.
     *
     * @return array{priority: ?string, label_id: ?int, blocked: bool}
     */
    protected function fieldFilters(Request $request): array
    {
        $priority = $request->input('priority');

        return [
            'priority' => $priority === TaskService::NONE || isset(Task::PRIORITIES[$priority]) ? $priority : null,
            'label_id' => $request->filled('label_id') ? (int) $request->integer('label_id') : null,
            'blocked' => $request->boolean('blocked'),
        ];
    }
}

<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\TaskRequest;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use App\Services\QuickCaptureService;
use App\Services\TaskService;
use App\Support\Duration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/** The task form's rules, with quick capture's default: it's yours unless you name someone. */
class CreateTask extends ActionTool
{
    public function __construct(private readonly QuickCaptureService $capture) {}

    public function name(): string
    {
        return 'create_task';
    }

    public function description(): string
    {
        return 'Draft a task for the person to confirm. It goes to them unless you name someone; send assignee_id null to leave it unassigned. Use list_teams_and_releases and find_people for ids. Someone on several teams must choose one.';
    }

    public function properties(): array
    {
        return [
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'priority' => ['type' => 'string', 'enum' => array_keys(Task::PRIORITIES)],
            'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'estimate' => ['type' => 'string', 'description' => 'Like "4h" or "1h 30m".'],
            'assignee_id' => ['type' => ['integer', 'null']],
            'team_id' => ['type' => 'integer'],
            'release_id' => ['type' => 'integer'],
        ];
    }

    public function required(): array
    {
        return ['title'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $payload = $input;
        if (! array_key_exists('assignee_id', $payload)) {
            $payload['assignee_id'] = $user->id;
        }
        if ($due = self::readable($payload['due_date'] ?? null)) {
            $payload['due_date'] = $due->toDateString();
        }

        $checked = FormRules::check(TaskRequest::class, $payload, $user)->validated();

        $release = filled($checked['release_id'] ?? null) ? Release::find($checked['release_id']) : null;
        if (! $user->can('create', [Task::class, $release])) {
            throw new AuthorizationException('This person can’t add tasks to that release.');
        }

        $teamId = TaskService::teamFor($release?->id, isset($checked['team_id']) ? (int) $checked['team_id'] : null, isset($checked['assignee_id']) ? (int) $checked['assignee_id'] : null);
        $due = filled($checked['due_date'] ?? null) ? Carbon::parse($checked['due_date']) : null;

        return new ActionDraft($checked, [
            'kind' => 'task',
            'action' => 'Create task',
            'title' => $checked['title'],
            'status' => Task::STATUSES[$checked['status'] ?? TaskService::DEFAULT_STATUS],
            'priority' => $checked['priority'] ?? null,
            'priority_label' => filled($checked['priority'] ?? null) ? Task::PRIORITIES[$checked['priority']] : null,
            'assignee' => self::person(filled($checked['assignee_id'] ?? null) ? User::find($checked['assignee_id']) : null),
            'due' => $due ? self::dayLabel($due) : null,
            'fields' => array_values(array_filter([
                $teamId ? ['label' => 'Team', 'value' => Team::find($teamId)?->name] : null,
                $release ? ['label' => 'Release', 'value' => $release->name] : null,
                filled($checked['estimate_minutes'] ?? null) ? ['label' => 'Estimate', 'value' => Duration::format((int) $checked['estimate_minutes'])] : null,
            ])),
            'body' => $checked['description'] ?? null,
            'warning' => $due && $due->isBefore(today()) ? 'This due date has already passed.' : null,
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $task = $this->capture->capture($input, $user);

        return new ActionResult('Task created', route('tasks.show', $task), "Created task [T{$task->id}].", [
            'ref' => "T{$task->id}", 'kind' => 'Task', 'title' => $task->title, 'url' => route('tasks.show', $task),
        ]);
    }
}

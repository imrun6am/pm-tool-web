<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\TaskTimeLogRequest;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use App\Services\TaskTimeService;
use App\Support\Duration;
use Illuminate\Support\Carbon;

class LogTime extends ActionTool
{
    public function __construct(private readonly TaskTimeService $time) {}

    public function name(): string
    {
        return 'log_time';
    }

    public function description(): string
    {
        return "Draft a time entry on a task, under the person's own name, for them to confirm.";
    }

    public function properties(): array
    {
        return [
            'task_id' => ['type' => 'integer'],
            'duration' => ['type' => 'string', 'description' => 'Like "1h 30m".'],
            'logged_on' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
            'note' => ['type' => 'string'],
        ];
    }

    public function required(): array
    {
        return ['task_id', 'duration'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $task = $this->visibleTask($input['task_id'] ?? null, $user);

        $payload = array_intersect_key($input, array_flip(['duration', 'minutes', 'note']));
        $payload['logged_on'] = self::day($input['logged_on'] ?? null);

        $checked = FormRules::check(TaskTimeLogRequest::class, $payload, $user, ['task' => $task])->validated();
        $day = Carbon::parse($checked['logged_on']);

        return new ActionDraft(['task_id' => $task->id] + $checked, [
            'kind' => 'change',
            'action' => 'Log time',
            'title' => $task->title,
            'eyebrow' => "Time on task #{$task->id}",
            'rows' => [
                ['label' => 'Time', 'from' => null, 'to' => Duration::format((int) $checked['minutes'])],
                ['label' => 'On', 'from' => null, 'to' => $day->isToday() ? 'Today' : self::dayLabel($day)],
            ],
            'body' => $checked['note'] ?? null,
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $task = $this->visibleTask($input['task_id'], $user);
        $this->time->log($task, $user, collect($input)->except('task_id')->all());

        return new ActionResult('Time logged', route('tasks.show', $task), "Logged time on task [T{$task->id}].", [
            'ref' => "T{$task->id}", 'kind' => 'Task', 'title' => $task->title, 'url' => route('tasks.show', $task),
        ]);
    }
}

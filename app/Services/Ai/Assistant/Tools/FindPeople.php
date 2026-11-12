<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\Task;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;

class FindPeople extends ReadTool
{
    private const LIMIT = 25;

    public function name(): string
    {
        return 'find_people';
    }

    public function description(): string
    {
        return 'Find active people this person may pick, with their ids, roles, teams and how many open tasks they have. Always use it to turn a name into an id before assigning or inviting someone.';
    }

    public function status(): string
    {
        return 'Looking up people';
    }

    public function properties(): array
    {
        return [
            'name' => ['type' => 'string', 'description' => 'Part of a name.'],
            'team_id' => ['type' => 'integer'],
        ];
    }

    public function run(array $input, User $user): ToolOutput
    {
        $people = User::active()->visibleTo($user)
            ->with('teams:id,name')
            ->when(filled($input['name'] ?? null), fn ($q) => $q->where('name', 'like', '%'.addcslashes((string) $input['name'], '%_\\').'%'))
            ->when(filled($input['team_id'] ?? null), fn ($q) => $q->whereHas('teams', fn ($t) => $t->whereKey((int) $input['team_id'])))
            ->orderBy('name')->limit(self::LIMIT)->get();

        // A shared task counts for each of its assignees.
        $open = Task::visibleTo($user)->counted()->whereNull('tasks.parent_id')
            ->whereNotIn('tasks.status', Task::DONE_STATUSES)
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->whereIn('task_user.user_id', $people->pluck('id'))
            ->selectRaw('task_user.user_id as user_id, count(*) as n')->groupBy('task_user.user_id')->pluck('n', 'user_id');

        $lines = $people->map(fn (User $p) => sprintf('%s (id %d), %s; teams: %s; open tasks: %d.',
            $p->name, $p->id, $p->roleLabel(), $p->teams->pluck('name')->implode(', ') ?: 'none', (int) ($open[$p->id] ?? 0)));

        return new ToolOutput($lines->isEmpty() ? 'Nobody matches among the people this person may pick.' : $lines->implode("\n"));
    }
}

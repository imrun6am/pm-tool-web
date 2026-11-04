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

        $open = Task::visibleTo($user)->counted()->whereNull('parent_id')
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->whereIn('assignee_id', $people->pluck('id'))
            ->selectRaw('assignee_id, count(*) as n')->groupBy('assignee_id')->pluck('n', 'assignee_id');

        $lines = $people->map(fn (User $p) => sprintf('%s (id %d), %s; teams: %s; open tasks: %d.',
            $p->name, $p->id, $p->roleLabel(), $p->teams->pluck('name')->implode(', ') ?: 'none', (int) ($open[$p->id] ?? 0)));

        return new ToolOutput($lines->isEmpty() ? 'Nobody matches among the people this person may pick.' : $lines->implode("\n"));
    }
}

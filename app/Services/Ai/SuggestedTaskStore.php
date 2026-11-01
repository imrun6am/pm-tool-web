<?php

namespace App\Services\Ai;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Creates the tasks a user accepted from a Claude suggestion (meeting action
 * items, document requirements, task-writer subtasks). The same team rules as
 * the board apply on the server: developers and QA file into their own team
 * and may assign only themselves or a teammate; an assignee the actor may not
 * pick is dropped rather than trusted.
 */
class SuggestedTaskStore
{
    public const MAX = 30;

    public function __construct(private readonly TaskService $tasks) {}

    /** Validation rules for a `tasks` array posted from a suggestion list. */
    public static function rules(): array
    {
        return [
            'tasks' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string', 'max:5000'],
            'tasks.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'tasks.*.due_date' => ['nullable', 'date'],
            'tasks.*.phase' => ['nullable', Rule::in(array_keys(Release::PHASES))],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items  validated `tasks`
     * @return list<Task>
     */
    public function create(array $items, User $actor, ?Release $release = null, ?Task $parent = null): array
    {
        $assignable = $this->assignableIds($actor);
        $limitedTeam = $actor->hasLimitedAccess() && $actor->currentTeamIds()->count() === 1
            ? $actor->currentTeamIds()->first()
            : null;

        return DB::transaction(function () use ($items, $actor, $release, $parent, $assignable, $limitedTeam) {
            $created = [];
            foreach ($items as $item) {
                $assignee = isset($item['assignee_id']) && ($assignable === null || in_array((int) $item['assignee_id'], $assignable, true))
                    ? (int) $item['assignee_id']
                    : null;

                $attributes = [
                    'title' => trim($item['title']),
                    'description' => filled($item['description'] ?? null) ? trim($item['description']) : null,
                    'status' => 'todo',
                    'assignee_id' => $assignee,
                    'due_date' => $item['due_date'] ?? null,
                    'phase' => $release || $parent?->release_id ? ($item['phase'] ?? null) : null,
                ];

                $created[] = match (true) {
                    $parent !== null => $this->tasks->createSubtask($parent, $attributes, $actor),
                    $release !== null => $this->tasks->createForRelease($release, $attributes, $actor),
                    default => $this->tasks->create($attributes + ['team_id' => $limitedTeam], $actor),
                };
            }

            return $created;
        });
    }

    /** Who the actor may assign: everyone for leads and viewers (null), teammates for developers and QA. */
    private function assignableIds(User $actor): ?array
    {
        if (! $actor->hasLimitedAccess()) {
            return null;
        }

        return User::teammatesOf($actor)->pluck('users.id')->push($actor->id)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}

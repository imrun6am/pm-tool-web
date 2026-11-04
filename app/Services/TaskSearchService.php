<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Find tasks by title or key for the ⌘K palette (and the API's search). Only tasks
 * the viewer may see; open work first, then the most recently touched.
 */
class TaskSearchService
{
    public const LIMIT = 8;

    /** Shorter queries return nothing rather than half the table. */
    public const MIN_LENGTH = 2;

    /** @return Collection<int, Task> */
    public function search(User $viewer, string $query, int $limit = self::LIMIT): Collection
    {
        $query = trim($query);

        // "PM-123" or "123" also finds that task by its reference, first.
        $id = Task::idFromReference($query);

        if ($id === null && mb_strlen($query) < self::MIN_LENGTH) {
            return collect();
        }

        // `!` as the LIKE escape works the same on MySQL and SQLite.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
        $done = implode(',', array_map(fn ($s) => "'{$s}'", Task::DONE_STATUSES));

        return Task::query()
            ->visibleTo($viewer)
            ->with(['release', 'assignee'])
            ->where(fn ($q) => $q->whereRaw("title LIKE ? ESCAPE '!'", [$like])->when($id, fn ($q) => $q->orWhere('id', $id)))
            ->when($id, fn ($q) => $q->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$id]))
            ->orderByRaw("CASE WHEN status IN ({$done}) THEN 1 ELSE 0 END")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}

<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The one place a task's assignees change. It keeps `tasks.assignee_id` (the
 * lead) one of them, logs the change, and tells each person added or
 * removed, once. People who stay assigned hear nothing about it.
 */
class TaskAssignees
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Make `$userIds` the task's assignees, in that order. The lead is
     * `$leadId` when it's in the list, else the current lead if they stay,
     * else the first. `$direct` is a write of assignee_id alone, which the
     * model's own activity entry already describes.
     *
     * @param  iterable<int, int|string>  $userIds
     * @return array{added: list<int>, removed: list<int>}
     */
    public function sync(Task $task, iterable $userIds, ?int $leadId = null, ?User $actor = null, bool $direct = false): array
    {
        $actor ??= Auth::user();
        $ids = self::normalise($userIds);

        $before = $task->assignees()->get();
        $beforeIds = $before->pluck('id')->all();
        $added = array_values(array_diff($ids, $beforeIds));
        $removed = array_values(array_diff($beforeIds, $ids));

        $lead = match (true) {
            $leadId !== null && in_array($leadId, $ids, true) => $leadId,
            $task->assignee_id !== null && in_array((int) $task->assignee_id, $ids, true) => (int) $task->assignee_id,
            default => $ids[0] ?? null,
        };

        if (! $added && ! $removed && $lead === ($task->assignee_id === null ? null : (int) $task->assignee_id)) {
            return ['added' => [], 'removed' => []];
        }

        DB::transaction(function () use ($task, $added, $removed, $lead) {
            if ($removed) {
                $task->assignees()->detach($removed);
            }
            // One at a time, so the pivot's ids keep the order they were chosen in.
            foreach ($added as $id) {
                $task->assignees()->attach($id);
            }

            if ($lead !== ($task->assignee_id === null ? null : (int) $task->assignee_id)) {
                $task->syncingAssignees = true;
                try {
                    $task->forceFill(['assignee_id' => $lead])->saveQuietly();
                } finally {
                    $task->syncingAssignees = false;
                }
            }
        });

        $task->unsetRelation('assignees')->unsetRelation('assignee');

        // A direct single-assignee write is already in the log as assignee_id,
        // and a new task's "created" entry names its lead.
        $isNew = $task->wasRecentlyCreated && $beforeIds === [];
        if (! $isNew && (! $direct || count($beforeIds) > 1)) {
            $names = fn ($people) => $people->pluck('name')->sort()->implode(', ') ?: null;
            $task->recordFieldChange(
                ['assignees' => $names($before)],
                ['assignees' => $names($task->assignees()->get())],
            );
        }

        $this->notifications->batch(function () use ($task, $added, $removed, $actor) {
            $this->notifications->taskAssigned($task, $actor, $added);
            foreach ($removed as $id) {
                $this->notifications->taskUnassigned($task, $id, $actor, $added);
            }
        });

        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * The list a request asks for, or null to leave the assignees alone.
     * `assignee_ids` is the whole list. `assignee_id` on its own is what older
     * clients send: null clears, the current lead changes nothing, and anyone
     * else becomes the only assignee.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{ids: list<int>, lead: ?int}|null
     */
    public static function requested(array $attributes, ?Task $task = null): ?array
    {
        if (array_key_exists('assignee_ids', $attributes)) {
            $lead = isset($attributes['assignee_id']) ? (int) $attributes['assignee_id'] : null;

            return ['ids' => self::normalise($attributes['assignee_ids'] ?? []), 'lead' => $lead];
        }

        if (! array_key_exists('assignee_id', $attributes)) {
            return null;
        }

        $id = $attributes['assignee_id'] === null ? null : (int) $attributes['assignee_id'];
        if ($task && $id !== null && $id === $task->assignee_id) {
            return null; // the lead, unchanged: keep everyone
        }

        return ['ids' => $id === null ? [] : [$id], 'lead' => $id];
    }

    /**
     * @param  iterable<int, int|string|null>  $userIds
     * @return list<int>
     */
    public static function normalise(iterable $userIds): array
    {
        return collect($userIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(Task::MAX_ASSIGNEES)
            ->values()
            ->all();
    }
}

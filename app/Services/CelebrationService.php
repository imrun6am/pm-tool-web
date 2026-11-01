<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The small "nice work" moment when someone finishes a task. Personal and
 * quiet: only the person who completed it sees it, there are no points, and
 * it can be switched off (PreferenceService `celebrations`).
 */
class CelebrationService
{
    /** `:task` is replaced by the quoted task title. */
    public const MESSAGES = [
        'Nice work — :task is done.',
        'Done and dusted: :task.',
        'One less thing on your list. :task is finished.',
        'That’s a wrap on :task.',
        'Ticked off: :task. Keep it rolling.',
        'Good going — :task is complete.',
    ];

    public const ALL_CLEAR = 'All clear — :task was your last open task. Enjoy the empty plate.';

    public function __construct(private readonly PreferenceService $preferences) {}

    /** Whether a status change is a completion worth celebrating. */
    public static function isCompletion(?string $from, ?string $to): bool
    {
        return $to === 'done' && ! in_array($from, Task::DONE_STATUSES, true);
    }

    /**
     * The celebration for `$actor` finishing `$task`, or null when they opted out.
     *
     * @return array{message: string, all_clear: bool}|null
     */
    public function for(Task $task, User $actor): ?array
    {
        if (! $this->preferences->get($actor, 'celebrations')) {
            return null;
        }

        $allClear = (int) $task->assignee_id === $actor->id && ! Task::query()
            ->where('assignee_id', $actor->id)
            ->whereKeyNot($task->id)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->exists();

        $template = $allClear ? self::ALL_CLEAR : Arr::random(self::MESSAGES);

        return [
            'message' => str_replace(':task', '“'.Str::limit($task->title, 60).'”', $template),
            'all_clear' => $allClear,
        ];
    }
}

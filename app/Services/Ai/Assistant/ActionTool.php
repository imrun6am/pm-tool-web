<?php

namespace App\Services\Ai\Assistant;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * A change. draft() runs the page's form rules and policies and says what would
 * happen, changing nothing; run() does it through the page's service, and only
 * ever follows a person's confirmation. Both raise ValidationException or
 * AuthorizationException when the person may not do this.
 */
abstract class ActionTool extends AssistantTool
{
    abstract public function draft(array $input, User $user): ActionDraft;

    abstract public function run(array $input, User $user): ActionResult;

    protected function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** A task this person can open. One they can't is reported the same as one that doesn't exist. */
    protected function visibleTask(mixed $id, User $user): Task
    {
        $task = is_numeric($id) ? Task::visibleTo($user)->with(['assignee', 'release', 'team'])->find((int) $id) : null;

        return $task ?? $this->fail('task_id', 'No task with that id that this person can open.');
    }

    /** Today's date when none is given, in a stable form when one is readable. */
    protected static function day(mixed $value): mixed
    {
        return blank($value) ? today()->toDateString() : (self::readable($value)?->toDateString() ?? $value);
    }

    protected static function readable(mixed $value): ?Carbon
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Like "Tue, Nov 3", with the year only when it isn't this year. */
    protected static function dayLabel(Carbon $date): string
    {
        return $date->format($date->isSameYear(today()) ? 'D, M j' : 'D, M j, Y');
    }

    protected static function initials(?string $name): string
    {
        return collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }

    /** A person as a draft shows them. */
    protected static function person(?User $user): ?array
    {
        return $user ? ['name' => $user->name, 'initials' => self::initials($user->name)] : null;
    }
}

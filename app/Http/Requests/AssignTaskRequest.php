<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;

/**
 * Setting or clearing a card's assignee from the board, shared by the web and
 * API boards. It keeps TaskRequest's authorization and assignee rules, so
 * the board and the task form can't drift apart.
 */
class AssignTaskRequest extends TaskRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'assignee_id' => ['present', ...parent::rules()['assignee_id'], $this->assigneeIsActive(...)],
        ];
    }

    /**
     * Always the task's own release. This request changes nothing else, so a
     * `release_id` it carries must not move the team check to another release.
     */
    protected function targetRelease(?Task $task): ?Release
    {
        return $task?->release;
    }

    /** Someone deactivated can stay on a card, but can't newly be given one. */
    private function assigneeIsActive(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value === null || (int) $value === $this->route('task')->assignee_id) {
            return;
        }

        if (! User::active()->whereKey((int) $value)->exists()) {
            $fail('This person’s account is deactivated.');
        }
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Setting a card's assignees from the board, shared by the web and API
 * boards: `assignee_ids` for the whole list, or `assignee_id` alone as older
 * clients send it (null clears). It keeps TaskRequest's authorization and
 * assignee rules, so the board and the task form can't drift apart.
 */
class AssignTaskRequest extends TaskRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();

        return [
            'assignee_id' => [...$rules['assignee_id'], $this->assigneeIsActive(...)],
            'assignee_ids' => $rules['assignee_ids'],
            'assignee_ids.*' => [...$rules['assignee_ids.*'], $this->assigneeIsActive(...)],
        ];
    }

    /** One of the two must be sent; leaving both out changes nothing by mistake. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->has('assignee_id') && ! $this->has('assignee_ids')) {
                $validator->errors()->add('assignee_id', 'Choose who to assign, or send an empty list to unassign.');
            }
        }];
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
        if ($value === null || $this->route('task')->hasAssignee((int) $value)) {
            return;
        }

        if (! User::active()->whereKey((int) $value)->exists()) {
            $fail('This person’s account is deactivated.');
        }
    }
}

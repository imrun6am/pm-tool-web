<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\TasksheetEntry;
use App\Services\Board\PlanToBoardService;
use Illuminate\Contracts\Validation\Validator;

/**
 * Tasksheet "Add to board": the row's own fields plus the release the plan's
 * tasks go into, if any. A chosen release must belong to the row's team and
 * still be open, and there must be a plan to split.
 */
class TasksheetBoardRequest extends TasksheetEntryRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'board_release_id' => ['nullable', 'integer', 'exists:releases,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $release = $this->filled('board_release_id') ? Release::find($this->integer('board_release_id')) : null;
            if ($release && ((int) $release->team_id !== $this->integer('team_id') || $release->isComplete())) {
                $validator->errors()->add('board_release_id', 'Choose an open release of this team.');
            }

            if (in_array($this->input('leave_type'), TasksheetEntry::FULL_DAY_LEAVE_TYPES, true)) {
                $validator->errors()->add('leave_type', 'A full-day leave has no tasks to add to the board.');
            } elseif (PlanToBoardService::plainText((string) $this->input('plan')) === '') {
                $validator->errors()->add('plan', 'Write the task plan first — it is what gets added to the board.');
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use App\Models\TasksheetEntry;
use App\Models\Team;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TasksheetEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'plan' => ['nullable', 'string', 'max:20000'],
            'result' => ['nullable', 'string', 'max:20000'],
            'comment' => ['nullable', 'string', 'max:20000'],
            'tickets' => ['nullable', 'string', 'max:20000'],
            'work_points' => ['nullable', 'integer', 'min:0'],
            'ticket_count' => ['nullable', 'integer', 'min:0'],
            'ticket_points' => ['nullable', 'integer', 'min:0'],
            'leave_type' => ['nullable', Rule::in(array_keys(TasksheetEntry::LEAVE_TYPES))],
            'feedback' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * The row's member must belong to the team — or already have an entry for
     * that team and date (former members keep their historical rows editable).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $teamId = $this->integer('team_id');
            $userId = $this->integer('user_id');
            $date = Carbon::parse($this->input('date'))->toDateString();

            $isMember = Team::whereKey($teamId)
                ->whereHas('members', fn ($q) => $q->whereKey($userId))
                ->exists();
            $hasEntry = TasksheetEntry::where('team_id', $teamId)
                ->where('user_id', $userId)
                ->whereDate('date', $date)
                ->exists();

            if (! $isMember && ! $hasEntry) {
                $validator->errors()->add('user_id', 'That person is not a member of this team.');
            }
        });
    }
}

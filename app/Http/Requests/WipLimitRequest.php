<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Setting or clearing a column's limit, shared by web and API. A team's own lead or an org-level lead. */
class WipLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = Team::find($this->integer('team_id'));

        return ! $team || $this->user()->canAccessTeamPerformance($team);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')],
            'status' => ['required', Rule::in(array_diff(array_keys(Task::STATUSES), Task::DONE_STATUSES))],
            'max_tasks' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}

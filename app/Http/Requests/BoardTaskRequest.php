<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\KeepsTasksInViewerTeams;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Board quick-add, shared by the web and API boards: a title and column, an
 * optional release, and a team (pinned for developers and QA).
 */
class BoardTaskRequest extends FormRequest
{
    use KeepsTasksInViewerTeams;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->pinViewerTeam(creating: true);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->withViewerTeamRules([
            'release_id' => ['nullable', Rule::exists('releases', 'id')],
            'team_id' => ['nullable', Rule::exists('teams', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ], creating: true);
    }
}

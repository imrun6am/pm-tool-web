<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\KeepsTasksInViewerTeams;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Board quick-add, shared by the web and API boards: a title and column, an
 * optional release and assignee, and a team (pinned for developers and QA).
 * A card a developer or QA adds is theirs unless they pick a teammate.
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
        if (blank($this->input('assignee_id'))) {
            $this->merge(['assignee_id' => $this->user()?->hasLimitedAccess() ? $this->user()->id : null]);
        }

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
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at'), $this->assigneeOnReleaseTeam(...)],
        ], creating: true);
    }

    /** As on the task form: a card linked to a release goes to someone on that release's team. */
    private function assigneeOnReleaseTeam(string $attribute, mixed $value, \Closure $fail): void
    {
        $release = $this->filled('release_id') ? Release::find($this->integer('release_id')) : null;

        if ($release && $value !== null && ! $release->team->members()->whereKey((int) $value)->exists()) {
            $fail('The assignee must be a member of the release’s team.');
        }
    }
}

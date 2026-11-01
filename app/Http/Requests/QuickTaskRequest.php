<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\KeepsTasksInViewerTeams;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Quick capture: a title, optional due date, assignee (defaults to the
 * author) and an optional ongoing release or team. Developers and QA are kept
 * to their own teams and teammates, as on the board.
 */
class QuickTaskRequest extends FormRequest
{
    use KeepsTasksInViewerTeams;

    public function authorize(): bool
    {
        $release = $this->filled('release_id') ? Release::find($this->integer('release_id')) : null;

        return $this->user()?->can('create', [Task::class, $release]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // A blank select means "not set", not the string "".
        foreach (['release_id', 'team_id', 'due_date'] as $field) {
            if ($this->has($field) && blank($this->input($field))) {
                $this->merge([$field => null]);
            }
        }
        if ($this->has('assignee_id') && blank($this->input('assignee_id'))) {
            $this->merge(['assignee_id' => null]);
        }

        $this->pinViewerTeam(creating: true);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->withViewerTeamRules([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deactivated_at')],
            'release_id' => ['nullable', Rule::exists('releases', 'id')->whereNull('completed_at')],
            'team_id' => ['nullable', Rule::exists('teams', 'id')],
        ], creating: true);
    }
}

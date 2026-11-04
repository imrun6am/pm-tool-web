<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Services\BoardService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Bulk delete from the board, shared by the web and API boards.
 *
 * The role is checked first, so anyone else gets a 403 whatever they send and
 * can't learn which ids exist. Then every id is validated as an existing card,
 * and every loaded task is authorized on its own.
 */
class BulkDeleteTasksRequest extends FormRequest
{
    /** @var Collection<int, Task>|null */
    private ?Collection $cards = null;

    public function authorize(): bool
    {
        return $this->user()?->can('deleteFromBoard', Task::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.BoardService::MAX_BULK_DELETE],
            'ids.*' => ['required', 'integer', 'distinct:strict', Rule::exists('tasks', 'id')->whereNull('parent_id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.*.exists' => 'Task :input no longer exists or is not a card on the board.',
        ];
    }

    protected function passedValidation(): void
    {
        $gate = Gate::forUser($this->user());

        $this->tasks()->each(fn (Task $task) => $gate->authorize('deleteFromBoard', $task));
    }

    /** @return Collection<int, Task> */
    public function tasks(): Collection
    {
        return $this->cards ??= Task::whereKey($this->validated('ids'))->get();
    }
}

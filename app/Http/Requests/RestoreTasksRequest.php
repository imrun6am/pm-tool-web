<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Bringing tasks back from the trash, shared by web and API. Admins only. */
class RestoreTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTrash', Task::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['required', 'integer', 'distinct:strict', Rule::exists('tasks', 'id')->whereNotNull('deleted_at')],
        ];
    }

    /** @return Collection<int, Task> */
    public function tasks(): Collection
    {
        return Task::onlyTrashed()->whereKey($this->validated('ids'))->get();
    }
}

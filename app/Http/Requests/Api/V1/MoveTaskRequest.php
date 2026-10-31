<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A board drag: the card's new status plus the full ordering of the column it
 * landed in. Both travel together because a move that changed status without
 * renumbering the column would leave the board's order undefined.
 */
class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'ordered_ids' => ['array'],
            'ordered_ids.*' => ['integer'],
        ];
    }
}

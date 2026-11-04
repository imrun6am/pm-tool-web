<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Marking a task as blocked, shared by web and API. */
class TaskBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => 'Say what the task is waiting for.'];
    }
}

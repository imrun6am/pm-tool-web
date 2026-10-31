<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['all_day' => $this->boolean('all_day')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Event::TYPES))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'release_id' => ['nullable', Rule::exists('releases', 'id')],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => [Rule::exists('users', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_at.after_or_equal' => 'The end must be on or after the start.',
        ];
    }
}

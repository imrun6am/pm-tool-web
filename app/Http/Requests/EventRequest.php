<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Rules\VisiblePerson;
use App\Rules\VisibleRelease;
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
        // An edit may keep the event's current release and attendees.
        $event = $this->route('event');
        $keptAttendees = $event instanceof Event ? $event->attendees()->pluck('users.id')->all() : [];
        $event = $event instanceof Event ? $event : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Event::TYPES))],
            'visibility' => ['sometimes', Rule::in(array_keys(Event::VISIBILITIES))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Developers and QA: only their own teams' releases and people.
            'release_id' => ['nullable', Rule::exists('releases', 'id'), new VisibleRelease($this->user(), $event?->release_id)],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => [Rule::exists('users', 'id'), new VisiblePerson($this->user(), $keptAttendees)],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_at.after_or_equal' => 'The end must be on or after the start.',
        ];
    }
}

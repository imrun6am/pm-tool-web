<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Rules\VisiblePerson;
use App\Rules\VisibleRelease;
use App\Services\Calendar\EventImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The review form: one row per proposal. Only ticked rows are validated (an
 * unticked row may stay half-edited); each must be a valid event.
 */
class ConfirmEventImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // the controller checks the import policy
    }

    public function rules(): array
    {
        $only = 'exclude_unless:events.*.include,1';

        return [
            'events' => ['required', 'array'],
            'events.*.include' => ['nullable', 'boolean'],
            'events.*.title' => [$only, 'required', 'string', 'max:255'],
            'events.*.type' => [$only, 'required', Rule::in(array_keys(Event::TYPES))],
            'events.*.visibility' => [$only, 'nullable', Rule::in(array_keys(Event::VISIBILITIES))],
            'events.*.date' => [$only, 'required', 'date_format:Y-m-d'],
            'events.*.all_day' => [$only, 'nullable', 'boolean'],
            'events.*.start_time' => [$only, 'nullable', 'date_format:H:i'],
            'events.*.end_date' => [$only, 'nullable', 'date_format:Y-m-d'],
            'events.*.end_time' => [$only, 'nullable', 'date_format:H:i'],
            'events.*.location' => [$only, 'nullable', 'string', 'max:255'],
            'events.*.description' => [$only, 'nullable', 'string', 'max:5000'],
            // Developers and QA: only their own teams' releases and people.
            'events.*.release_id' => [$only, 'nullable', Rule::exists('releases', 'id'), new VisibleRelease($this->user())],
            'events.*.attendees' => [$only, 'nullable', 'array'],
            'events.*.attendees.*' => [$only, Rule::exists('users', 'id'), new VisiblePerson($this->user())],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->input('events', []) as $index => $row) {
                if (! is_array($row) || empty($row['include'])) {
                    continue;
                }
                [$start, $end] = EventImportService::window($row);
                if ($end !== null && $end->lt($start)) {
                    $validator->errors()->add("events.{$index}.end_date", 'The end must be on or after the start.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'events.*.title' => 'title',
            'events.*.type' => 'type',
            'events.*.date' => 'date',
            'events.*.start_time' => 'start time',
            'events.*.end_date' => 'end date',
            'events.*.end_time' => 'end time',
        ];
    }
}

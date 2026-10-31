<?php

namespace App\Http\Requests;

use App\Models\MeetingNote;
use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * A submission that omits the type gets one rather than an error: the
     * default for a new note, and the note's current type when editing, so a
     * write that does not mention the type never silently re-categorizes it.
     * An explicit but unknown type still fails the `in` rule below.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('type')) {
            $this->merge([
                'type' => $this->route('meetingNote')?->type ?? MeetingNote::TYPE_DEFAULT,
            ]);
        }
    }

    public function rules(): array
    {
        // Only ongoing releases may be linked — except a note may keep the
        // release it is already linked to, even if that release completed since.
        $keptReleaseId = $this->route('meetingNote')?->release_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(MeetingNote::TYPES))],
            'meeting_date' => ['required', 'date'],
            'release_id' => ['nullable', 'integer',
                Rule::exists('releases', 'id')->where(
                    fn ($q) => $q->where(fn ($q) => $q->whereNull('completed_at')->orWhere('id', $keptReleaseId))
                ),
            ],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'body' => ['required', 'string', 'max:20000'],
            'visibility' => ['nullable', Rule::in(array_keys(MeetingNote::VISIBILITIES))],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'release_id.exists' => 'Completed releases cannot be linked to a new meeting note.',
        ];
    }

    /** Trix can submit markup with no visible text — treat that as empty. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->has('body') && HtmlSanitizer::isEmpty($this->input('body'))) {
                $validator->errors()->add('body', 'The note cannot be empty.');
            }
        });
    }
}

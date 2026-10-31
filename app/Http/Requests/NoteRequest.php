<?php

namespace App\Http\Requests;

use App\Models\Note;
use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:20000'],
            'visibility' => ['required', Rule::in(array_keys(Note::VISIBILITIES))],
            'date' => ['required', 'date'],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['integer', 'exists:users,id'],
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

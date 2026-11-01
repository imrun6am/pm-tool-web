<?php

namespace App\Http\Requests;

use App\Services\Calendar\EventImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** "Paste text" on the calendar import page: an email, chat message, agenda or iCalendar data. */
class EventImportTextRequest extends FormRequest
{
    /** Keeps a paste to at most three extraction chunks. */
    public const MAX_CHARS = 100000;

    public function authorize(): bool
    {
        // Same as uploading a file: any signed-in user.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:'.self::MAX_CHARS],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $text = $this->input('text');
            if (is_string($text) && ! $validator->errors()->has('text')
                && ! EventImportService::isIcs($text) && EventImportService::pastedText($text) === '') {
                $validator->errors()->add('text', 'There is no text to read. Paste the message or schedule itself.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Paste some text to find events in.',
            'text.max' => 'Paste at most '.number_format(self::MAX_CHARS).' characters, or upload the document as a file.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Services\Calendar\EventImportService;
use App\Services\TriageImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** "Paste text" on the Requests page: an email, chat message or list of asks. */
class TriageImportTextRequest extends FormRequest
{
    public const MAX_CHARS = EventImportTextRequest::MAX_CHARS;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:'.self::MAX_CHARS]];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('text')) {
                return;
            }

            if (EventImportService::pastedText($this->input('text')) === '') {
                $validator->errors()->add('text', 'There is no text to read. Paste the message itself.');
            } elseif (! app(TriageImportService::class)->available()) {
                $validator->errors()->add('text', 'Claude isn’t available to read text right now. You can still send a request with the form.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Paste some text to read.',
            'text.max' => 'Paste at most '.number_format(self::MAX_CHARS).' characters, or upload the document as a file.',
        ];
    }
}

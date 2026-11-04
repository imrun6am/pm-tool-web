<?php

namespace App\Http\Requests;

use App\Services\Calendar\EventImportService;
use App\Services\TriageImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Uploading a file or screenshot on the Requests page. */
class TriageImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same as sending a request: any signed-in user.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $types = implode(',', TriageImportService::EXTENSIONS);

        return [
            // The content must look like one of these *and* the name must say so.
            'document' => ['required', 'file', 'max:'.TriageImportService::MAX_FILE_KB, "mimes:{$types}", "extensions:{$types}"],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('document')) {
                return;
            }

            $extension = strtolower($this->file('document')->getClientOriginalExtension());
            if (in_array($extension, EventImportService::IMAGE_EXTENSIONS, true)
                && $this->file('document')->getSize() > TriageImportService::MAX_IMAGE_KB * 1024) {
                $validator->errors()->add('document', 'Images may not be larger than 5 MB.');
            }

            if (! app(TriageImportService::class)->available()) {
                $validator->errors()->add('document', 'Claude isn’t available to read files right now. You can still send a request with the form.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Choose a file to import.',
            'document.max' => 'The file may not be larger than 20 MB.',
            'document.mimes' => 'Allowed types: PDF, PNG, JPG, GIF, WebP, DOCX, XLSX, CSV, TXT, MD.',
            'document.extensions' => 'Allowed types: PDF, PNG, JPG, GIF, WebP, DOCX, XLSX, CSV, TXT, MD.',
        ];
    }
}

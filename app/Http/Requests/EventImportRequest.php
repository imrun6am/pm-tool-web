<?php

namespace App\Http\Requests;

use App\Services\Calendar\EventImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EventImportRequest extends FormRequest
{
    /** Claude's per-image limit. */
    private const MAX_IMAGE_KB = 5120;

    public function authorize(): bool
    {
        // Same as creating an event: any signed-in user.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $types = implode(',', EventImportService::EXTENSIONS);

        return [
            'document' => [
                'required',
                'file',
                'max:20480', // 20 MB
                // The content must look like one of these *and* the name must say so.
                "mimes:{$types}",
                "extensions:{$types}",
            ],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $file = $this->file('document');
            if ($file && in_array(strtolower($file->getClientOriginalExtension()), EventImportService::IMAGE_EXTENSIONS, true)
                && $file->getSize() > self::MAX_IMAGE_KB * 1024) {
                $validator->errors()->add('document', 'Images may not be larger than 5 MB.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Choose a file to import.',
            'document.max' => 'The file may not be larger than 20 MB.',
            'document.mimes' => 'Allowed types: PDF, PNG, JPG, GIF, WebP, ICS, DOCX, XLSX, CSV, TXT, MD.',
            'document.extensions' => 'Allowed types: PDF, PNG, JPG, GIF, WebP, ICS, DOCX, XLSX, CSV, TXT, MD.',
        ];
    }
}

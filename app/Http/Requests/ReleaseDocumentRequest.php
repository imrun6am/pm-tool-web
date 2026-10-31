<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Contributors (leads + developers/QA) may upload; pure viewers may not.
        $user = $this->user();

        return $user !== null && ! $user->isViewer();
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'max:20480', // 20 MB
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,png,jpg,jpeg,zip',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'document.max' => 'The file may not be larger than 20 MB.',
            'document.mimes' => 'Allowed types: pdf, doc(x), xls(x), ppt(x), txt, csv, png, jpg, zip.',
        ];
    }
}

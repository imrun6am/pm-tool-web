<?php

namespace App\Http\Requests;

use App\Models\SavedView;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Saving a view, shared by web and API. Saving under an existing name replaces that view. */
class SavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->boolean('shared') || $this->user()->can('share', SavedView::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['required', Rule::in(array_keys(SavedView::PAGES))],
            'name' => ['required', 'string', 'max:60'],
            'filters' => ['nullable', 'array'],
            'shared' => ['sometimes', 'boolean'],
        ];
    }
}

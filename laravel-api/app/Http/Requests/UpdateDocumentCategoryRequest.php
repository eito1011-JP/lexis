<?php

namespace App\Http\Requests;

use App\Rules\UniqueSlugInSameParent;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                new UniqueSlugInSameParent('document_categories', $this->route('category_path')),
            ],
            'sidebar_label' => 'required|string|max:255',
            'position' => 'required|integer',
            'description' => 'nullable|string',
        ];
    }
}

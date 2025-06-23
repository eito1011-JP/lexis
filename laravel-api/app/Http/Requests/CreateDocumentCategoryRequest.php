<?php

namespace App\Http\Requests;

use App\Rules\UniqueSlugInSameParent;
use Illuminate\Foundation\Http\FormRequest;

class CreateDocumentCategoryRequest extends FormRequest
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
            'slug' => [
                'required',
                'string',
                new UniqueSlugInSameParent($this->category_path),
            ],
            'sidebar_label' => 'required|string',
            'position' => 'nullable|numeric',
            'description' => 'nullable|string',
            'category_path' => 'array',
        ];
    }
}

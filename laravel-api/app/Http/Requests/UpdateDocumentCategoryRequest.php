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
            'slug' => [
                'required',
                'string',
                new UniqueSlugInSameParent($this->category_path, $this->current_category_id, null),
            ],
            'current_category_id' => 'required|integer',
            'category_path' => 'nullable|string|max:255',
            'sidebar_label' => 'required|string|max:255',
            'position' => 'required|integer',
            'description' => 'nullable|string',
            'edit_pull_request_id' => 'nullable|integer',
        ];
    }
}

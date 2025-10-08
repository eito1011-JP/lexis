<?php

namespace App\Http\Requests\Api\DocumentCategory;

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
            'category_entity_id' => 'required|integer|exists:category_entities,id',
            'title' => 'required|string',
            'description' => 'required|string',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'category_entity_id' => $this->route('category_entity'),
        ]);
    }
}

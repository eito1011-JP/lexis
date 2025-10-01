<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDocumentCategoryRequest extends FormRequest
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
            'edit_pull_request_id' => 'nullable|integer',
            'pull_request_edit_token' => 'nullable|string|max:255',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'category_entity_id' => $this->route('category_entity'),
        ]);
    }
}

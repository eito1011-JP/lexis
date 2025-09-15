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
            'category_id' => 'required|integer|exists:document_categories,id',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'edit_pull_request_id' => 'nullable|integer',
            'pull_request_edit_token' => 'nullable|string',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->route('id'),
        ]);
    }
}

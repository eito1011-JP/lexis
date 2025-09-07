<?php

namespace App\Http\Requests\Api\DocumentCategory;

use Illuminate\Foundation\Http\FormRequest;

class FetchCategoriesRequest extends FormRequest
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
            'parent_id' => 'nullable|integer|exists:document_categories,id',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.integer' => trans('validation.category.parent_id.integer'),
            'parent_id.exists' => trans('validation.category.parent_id.exists'),
        ];
    }
}

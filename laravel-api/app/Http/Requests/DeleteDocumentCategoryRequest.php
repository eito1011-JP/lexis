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
            'category_path' => 'required|string',
        ];
    }

    /**
     * バリデーション前にデータを準備
     */
    protected function prepareForValidation(): void
    {
        $categoryPath = $this->route('category_path');

        if ($categoryPath) {
            $this->merge(['category_path' => $categoryPath]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_path.required' => 'category_pathは必須です',
        ];
    }
}

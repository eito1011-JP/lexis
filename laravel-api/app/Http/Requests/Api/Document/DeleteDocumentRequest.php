<?php

namespace App\Http\Requests\Api\Document;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDocumentRequest extends FormRequest
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
            'category_path_with_slug' => 'required|string|max:255',
            'edit_pull_request_id' => 'nullable|integer',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_path_with_slug' => __('attributes.document.category_path_with_slug'),
            'edit_pull_request_id' => __('attributes.document.editPullRequestId'),
        ];
    }
}

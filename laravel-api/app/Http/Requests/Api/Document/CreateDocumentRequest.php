<?php

namespace App\Http\Requests\Api\Document;

use App\Rules\UniqueSlugInSameParent;
use Illuminate\Foundation\Http\FormRequest;

class CreateDocumentRequest extends FormRequest
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
            'category_path' => 'nullable|string',
            'sidebar_label' => 'required|string|max:255',
            'content' => 'required|string',
            'is_public' => 'boolean',
            'slug' => ['required', 'string', new UniqueSlugInSameParent($this->category_path)],
            'file_order' => 'nullable|integer',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_path' => __('validation.document..required'),
            'sidebar_label' => __('validation.document.label.required'),
            'content' => __('validation.document.content.required'),
            'slug' => __('validation.document.slug.required'),
            'file_order' => __('validation.document.file_order.integer'),
        ];
    }
}

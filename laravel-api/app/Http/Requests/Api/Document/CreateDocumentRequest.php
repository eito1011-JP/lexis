<?php

namespace App\Http\Requests\Api\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

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
        Log::info('createDocument request: ' . json_encode($this->all()));
        return [
            'category' => 'nullable|string',
            'label' => 'required|string|max:255',
            'content' => 'required|string',
            'isPublic' => 'boolean',
            'slug' => 'required|string|unique:document_versions,slug',
            'fileOrder' => 'nullable|integer',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'category' => __('attributes.document.category'),
            'label' => __('attributes.document.label'),
            'content' => __('attributes.document.content'),
            'slug' => __('attributes.document.slug'),
            'fileOrder' => __('attributes.document.fileOrder'),
        ];
    }
} 
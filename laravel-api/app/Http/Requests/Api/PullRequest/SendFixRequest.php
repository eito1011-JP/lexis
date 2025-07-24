<?php

namespace App\Http\Requests\Api\PullRequest;

use Illuminate\Foundation\Http\FormRequest;

class SendFixRequest extends FormRequest
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
            'document_versions' => 'nullable|array',
            'document_versions.*.id' => 'required|integer|exists:document_versions,id',
            'document_versions.*.content' => 'required|string',
            'document_versions.*.sidebar_label' => 'required|string|max:255',
            'document_versions.*.slug' => 'required|string',
            'document_categories' => 'nullable|array',
            'document_categories.*.id' => 'required|integer|exists:document_categories,id',
            'document_categories.*.sidebar_label' => 'required|string|max:255',
            'document_categories.*.description' => 'required|string',
            'document_categories.*.slug' => 'required|string',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('validation.attributes.title'),
            'description' => __('validation.attributes.description'),
            'document_versions' => __('validation.attributes.document_versions'),
            'document_categories' => __('validation.attributes.document_categories'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // URLパラメータのidをマージ
        $this->merge([
            'pull_request_id' => $this->route('id'),
        ]);
    }
}

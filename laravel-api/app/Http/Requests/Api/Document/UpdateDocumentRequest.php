<?php

namespace App\Http\Requests\Api\Document;

use Illuminate\Foundation\Http\FormRequest;

// use League\HTMLToMarkdown\HtmlConverter;

class UpdateDocumentRequest extends FormRequest
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
            'document_entity_id' => 'required|integer|exists:document_entities,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'edit_pull_request_id' => 'nullable|integer',
            'pull_request_edit_token' => 'nullable|string',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'document_entity_id' => __('attributes.document.documentEntityId'),
            'title' => __('attributes.document.title'),
            'description' => __('attributes.document.description'),
            'edit_pull_request_id' => __('attributes.document.editPullRequestId'),
            'pull_request_edit_token' => __('attributes.document.pullRequestEditToken'),
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        $this->merge([
            'document_entity_id' => (int) $this->document_entity_id,
            'edit_pull_request_id' => $this->edit_pull_request_id ? (int) $this->edit_pull_request_id : null,
        ]);
    }
}

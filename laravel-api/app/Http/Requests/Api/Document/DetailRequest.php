<?php

namespace App\Http\Requests\Api\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class DetailRequest extends FormRequest
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
        Log::info(json_encode($this->all()));
        return [
            'entity_id' => 'required|integer|exists:document_version_entities,id',
            'pull_request_edit_session_token' => 'nullable|string',
        ];
    }

    /**
     * パラメータの準備
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'entity_id' => $this->route('document_version_entity'),
        ]);
    }
}

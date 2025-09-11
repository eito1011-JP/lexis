<?php

namespace App\Http\Requests\Api\DocumentCategory;

use Illuminate\Foundation\Http\FormRequest;

class FetchCategoriesRequest extends FormRequest
{
    /**
     * リクエストのauthorizationを処理
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * リクエストのvalidationルールを取得
     */
    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|integer|exists:document_categories,id',
            'pull_request_edit_session_token' => 'nullable|string|size:32',
        ];
    }

    /**
     * validationエラーメッセージをカスタマイズ
     */
    public function messages(): array
    {
        return [
            'parent_id.integer' => __('validation.category.parent_id.integer'),
            'parent_id.exists' => __('validation.category.parent_id.exists'),
            'pull_request_edit_session_token.string' => __('validation.token.string'),
            'pull_request_edit_session_token.size' => __('validation.token.size'),
        ];
    }
}

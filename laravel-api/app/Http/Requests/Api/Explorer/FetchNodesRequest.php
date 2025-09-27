<?php

namespace App\Http\Requests\Api\Explorer;

use Illuminate\Foundation\Http\FormRequest;

class FetchNodesRequest extends FormRequest
{
    /**
     * リクエストが認可されているかを判定
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * リクエストに適用するバリデーションルール
     */
    public function rules(): array
    {
        return [
            'category_entity_id' => 'required|integer|exists:document_categories,entity_id',
            'pull_request_edit_session_token' => 'nullable|string',
        ];
    }
}

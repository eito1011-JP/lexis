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
            'parent_entity_id' => 'nullable|integer|exists:category_entities,id',
        ];
    }

    /**
     * validationエラーメッセージをカスタマイズ
     */
    public function messages(): array
    {
        return [
            'parent_entity_id.integer' => __('validation.category.parent_entity_id.integer'),
            'parent_entity_id.exists' => __('validation.category.parent_entity_id.exists'),
        ];
    }
}

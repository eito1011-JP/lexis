<?php

namespace App\Http\Requests\Api\DocumentCategory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * カテゴリ詳細取得リクエスト
 */
class GetCategoryRequest extends FormRequest
{
    /**
     * リクエストが認証されているかを判定
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールを取得
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_entity_id' => 'required|integer|min:1|exists:category_entities,id',
        ];
    }

    /**
     * バリデーション用のデータを準備
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_entity_id' => intval($this->route('category_entity')),
        ]);
    }
}

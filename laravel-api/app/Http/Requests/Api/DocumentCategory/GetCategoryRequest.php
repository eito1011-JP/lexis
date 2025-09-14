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
     *
     * @return bool
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
            'id' => 'required|integer|min:1',
        ];
    }

    /**
     * バリデーション用のデータを準備
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }
}

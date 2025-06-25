<?php

namespace App\Http\Requests\Api\Document;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDocumentRequest extends FormRequest
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
            'category_path' => 'nullable|string|max:255',
            'slug' => 'required|string|max:255',
        ];
    }

    /**
     * document削除はcategory_pathには該当のドキュメントのslugも含めたpathが渡される
     */
    public function prepareForValidation(): void
    {
        // URLパラメータから値を取得
        $categoryPath = $this->route('category_path');

        if ($categoryPath) {
            // URLデコード(パスパラメータは/一個区切りの値しか認識しないため)
            $decodedPath = urldecode($categoryPath);

            // パスを分割してカテゴリパスとスラッグを分離
            $pathParts = explode('/', $decodedPath);
            $slug = array_pop($pathParts); // 最後の要素がスラッグ
            $categoryPathOnly = implode('/', $pathParts); // 残りがカテゴリパス

            $this->merge([
                'category_path' => $categoryPathOnly,
                'slug' => $slug,
            ]);
        }
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_path' => __('attributes.document.category_path'),
            'slug' => __('attributes.document.slug'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_path.required' => 'category_pathは必須です',
            'category_path.string' => 'category_pathは文字列である必要があります',
            'category_path.max' => 'category_pathは255文字以内である必要があります',
            'slug.required' => 'slugは必須です',
            'slug.string' => 'slugは文字列である必要があります',
            'slug.max' => 'slugは255文字以内である必要があります',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Rules\UniqueSlugInSameParent;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentCategoryRequest extends FormRequest
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
            'slug' => [
                'required',
                'string',
                new UniqueSlugInSameParent($this->category_path, $this->current_category_id, null),
            ],
            'current_category_id' => 'required|integer',
            'category_path' => 'nullable|string|max:255',
            'sidebar_label' => 'required|string|max:255',
            'position' => 'required|integer',
            'description' => 'nullable|string',
        ];
    }

    /**
     * category_pathには該当のドキュメントのslugも含めたpathが渡される
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
}

<?php

namespace App\Services;

use App\Models\DocumentCategory;
use App\Models\DocumentVersion;

class DocumentCategoryService
{
    /**
     * positionの重複処理・自動採番を行う
     *
     * @param  int|null  $requestedPosition  リクエストされたposition
     * @param  int|null  $parentId  親カテゴリID
     * @return int 正規化されたposition
     */
    public function normalizePosition(?int $requestedPosition, ?int $parentId = null): int
    {
        if ($requestedPosition) {
            // position重複時、既存のposition >= 入力値を+1してずらす
            DocumentCategory::where('parent_id', $parentId)
                ->where('position', '>=', $requestedPosition)
                ->increment('position');

            return $requestedPosition;
        } else {
            // position未入力時、親カテゴリ内最大値+1をセット
            $maxPosition = DocumentCategory::where('parent_id', $parentId)
                ->max('position') ?? 0;

            return $maxPosition + 1;
        }
    }

    /**
     * カテゴリツリーを取得（slugから）
     *
     * @param  string  $slug
     * @param  int  $userBranchId
     */
    public function getCategoryTreeFromSlug(string $categoryPath): array
    {
        // カテゴリパスを取得
        $categoryPath = array_filter(explode('/', $categoryPath));

        // ルートカテゴリIDを取得
        $rootCategoryId = DocumentCategory::getIdFromPath($categoryPath);

        if (! $rootCategoryId) {
            return ['categories' => [], 'documents' => []];
        }

        // 削除対象のカテゴリとその子カテゴリを再帰的に取得
        $categories = $this->getCategoriesRecursively($rootCategoryId);

        // カテゴリに属するドキュメントを取得
        $categoryIds = collect($categories)->pluck('id')->toArray();
        $documents = DocumentVersion::whereIn('category_id', $categoryIds)->get();

        return [
            'categories' => $categories,
            'documents' => $documents,
        ];
    }

    /**
     * カテゴリを再帰的に取得
     */
    private function getCategoriesRecursively(int $categoryId): array
    {
        $categories = [];

        // 現在のカテゴリを取得
        $category = DocumentCategory::find($categoryId);
        if ($category) {
            $categories[] = $category;

            // 子カテゴリを再帰的に取得
            $childCategories = DocumentCategory::where('parent_id', $categoryId)->get();
            foreach ($childCategories as $childCategory) {
                $categories = array_merge($categories, $this->getCategoriesRecursively($childCategory->id));
            }
        }

        return $categories;
    }
}

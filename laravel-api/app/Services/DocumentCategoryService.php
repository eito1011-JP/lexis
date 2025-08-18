<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Models\DocumentCategory;

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
     * カテゴリパスからparentとなるcategory idを再帰的に取得
     *
     * @param  array  $categoryPath
     *                               parent/child/grandchildのカテゴリパスの場合, リクエストとして期待するのは['parent', 'child', 'grandchild']のような配列
     * @return int|null カテゴリID
     */
    public function getIdFromPath(array $categoryPath): ?int
    {
        if (empty($categoryPath)) {
            return DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
        }

        // デフォルトカテゴリ（uncategorized）から開始
        $parentId = DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
        $currentParentCategoryId = null;

        foreach ($categoryPath as $slug) {
            $category = DocumentCategory::where('slug', $slug)
                ->where('parent_id', $parentId)
                ->first();

            if (! $category) {
                return DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
            }

            $currentParentCategoryId = $category->id;
            $parentId = $category->id;
        }

        return $currentParentCategoryId;
    }
}

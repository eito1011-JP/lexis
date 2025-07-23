<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Models\DocumentVersion;

class DocumentService
{
    /**
     * file_orderの重複処理・自動採番を行う
     *
     * @param  int|null  $requestedFileOrder  リクエストされたfile_order
     * @param  int|null  $categoryId  カテゴリID
     * @return int 正規化されたfile_order
     */
    public function normalizeFileOrder(?int $requestedFileOrder, ?int $categoryId = null): int
    {
        $targetCategoryId = $categoryId ?? DocumentCategoryConstants::DEFAULT_CATEGORY_ID;

        if ($requestedFileOrder) {
            // file_order重複時、既存のfile_order >= 入力値を+1してずらす
            DocumentVersion::where('category_id', $targetCategoryId)
                ->where('status', 'merged')
                ->where('file_order', '>=', $requestedFileOrder)
                ->where('is_deleted', 0)
                ->increment('file_order');

            return $requestedFileOrder;
        } else {
            // file_order未入力時、カテゴリ内最大値+1をセット
            $maxOrder = DocumentVersion::where('category_id', $targetCategoryId)
                ->max('file_order') ?? 0;

            return $maxOrder + 1;
        }
    }
}

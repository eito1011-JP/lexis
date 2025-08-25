<?php

namespace App\Repositories\Interfaces;

use App\Models\DocumentVersion;

/**
 * ドキュメントバージョンRepositoryのインターフェース
 */
interface DocumentVersionRepositoryInterface
{
    /**
     * カテゴリIDとスラッグでドキュメントを取得
     *
     * @param  int  $categoryId  カテゴリID
     * @param  string  $slug  スラッグ
     * @return DocumentVersion|null ドキュメントバージョン
     */
    public function findByCategoryAndSlug(int $categoryId, string $slug): ?DocumentVersion;
}

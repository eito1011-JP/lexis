<?php

namespace App\Repositories;

use App\Models\DocumentVersion;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;

/**
 * ドキュメントバージョンRepository
 *
 * DocumentVersionモデルのデータアクセス層を担当
 */
class DocumentVersionRepository implements DocumentVersionRepositoryInterface
{
    /**
     * コンストラクタ
     *
     * @param  DocumentVersion  $model  ドキュメントバージョンモデル
     */
    public function __construct(
        private DocumentVersion $model
    ) {}

    /**
     * カテゴリIDとスラッグでドキュメントを取得
     *
     * @param  int  $categoryId  カテゴリID
     * @param  string  $slug  スラッグ
     * @return DocumentVersion|null ドキュメントバージョン
     */
    public function findByCategoryAndSlug(int $categoryId, string $slug): ?DocumentVersion
    {
        return $this->model->byCategory($categoryId)
            ->bySlug($slug)
            ->first();
    }
}

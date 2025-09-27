<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\GetCategoryDto;
use App\Models\DocumentCategory;
use App\Models\DocumentCategoryEntity;
use Http\Discovery\Exception\NotFoundException;

/**
 * カテゴリ詳細取得UseCase
 */
class GetCategoryUseCase
{
    /**
     * カテゴリ詳細を取得
     *
     * @throws NotFoundException
     */
    public function execute(GetCategoryDto $dto): array
    {
        $categoryEntity = DocumentCategoryEntity::find($dto->categoryEntityId);

        // entityに紐づくdocumentCategoryの
        $category = DocumentCategory::with(['parent.parent.parent.parent.parent.parent.parent']) // 7階層まで親カテゴリを読み込み
            ->where('id', $categoryEntity->id)
            ->where('organization_id', $dto->user->organizationMember->organization_id)
            ->first();

        if (! $category) {
            throw new NotFoundException('カテゴリが見つかりません。');
        }

        // パンクズリストを生成
        $breadcrumbs = $category->getBreadcrumbs();

        return [
            'id' => $category->id,
            'entity_id' => $category->entity_id,
            'title' => $category->title,
            'description' => $category->description,
            'breadcrumbs' => $breadcrumbs,
        ];
    }
}

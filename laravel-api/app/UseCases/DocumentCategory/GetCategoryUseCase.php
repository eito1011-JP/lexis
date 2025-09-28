<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\GetCategoryDto;
use App\Models\DocumentCategoryEntity;
use App\Services\DocumentCategoryService;
use Http\Discovery\Exception\NotFoundException;

/**
 * カテゴリ詳細取得UseCase
 */
class GetCategoryUseCase
{
    public function __construct(
        private DocumentCategoryService $documentCategoryService
    ) {}

    /**
     * カテゴリ詳細を取得
     *
     * @throws NotFoundException
     */
    public function execute(GetCategoryDto $dto): array
    {
        $categoryEntity = DocumentCategoryEntity::find($dto->categoryEntityId);

        if (!$categoryEntity) {
            throw new NotFoundException('カテゴリエンティティが見つかりません。');
        }

        // 作業コンテキストに応じて適切なカテゴリを取得
        $category = $this->documentCategoryService->getCategoryByWorkContext(
            $dto->categoryEntityId,
            $dto->user,
            $dto->pullRequestEditSessionToken
        );

        if (!$category) {
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

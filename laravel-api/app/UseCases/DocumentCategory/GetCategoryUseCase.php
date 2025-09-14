<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\GetCategoryDto;;
use App\Models\DocumentCategory;
use Http\Discovery\Exception\NotFoundException;

/**
 * カテゴリ詳細取得UseCase
 */
class GetCategoryUseCase
{
    /**
     * カテゴリ詳細を取得
     *
     * @param GetCategoryDto $dto
     * @return array
     * @throws NotFoundException
     */
    public function execute(GetCategoryDto $dto): array
    {
        $category = DocumentCategory::where('id', $dto->id)
            ->where('organization_id', $dto->user->organizationMember->organization_id)
            ->first();

        if (!$category) {
            throw new NotFoundException('カテゴリが見つかりません。');
        }

        return [
            'id' => $category->id,
            'title' => $category->title,
            'description' => $category->description,
        ];
    }
}

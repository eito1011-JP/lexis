<?php

namespace App\Dto\UseCase\DocumentCategory;

use App\Dto\UseCase\UseCaseDto;

/**
 * カテゴリ更新のDTOクラス
 */
class UpdateDocumentCategoryDto extends UseCaseDto
{
    /**
     * コンストラクタ
     */
    public function __construct(
        public readonly int $categoryEntityId,
        public readonly string $title,
        public readonly string $description,
    ) {}

    /**
     * リクエストからDTOを作成
     */
    public static function fromRequest(array $requestData): self
    {
        return new self(
            categoryEntityId: $requestData['category_entity_id'],
            title: $requestData['title'],
            description: $requestData['description'],
        );
    }
}

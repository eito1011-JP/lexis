<?php

namespace App\Dto\UseCase\DocumentCategory;

use App\Dto\UseCase\UseCaseDto;

class FetchCategoriesDto extends UseCaseDto
{
    public function __construct(
        public readonly ?int $parentEntityId = null,
    ) {}

    /**
     * リクエストデータからDTOを作成
     */
    public static function fromRequest(array $requestData): self
    {
        return new self(
            parentEntityId: $requestData['parent_entity_id'] ?? null,
        );
    }
}

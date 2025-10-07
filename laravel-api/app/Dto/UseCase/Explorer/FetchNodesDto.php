<?php

namespace App\Dto\UseCase\Explorer;

use App\Dto\UseCase\UseCaseDto;

class FetchNodesDto extends UseCaseDto
{
    public function __construct(
        public readonly int $categoryEntityId,
    ) {}

    /**
     * リクエストデータからDTOを作成
     */
    public static function fromRequest(array $requestData): self
    {
        return new self(
            categoryEntityId: $requestData['category_entity_id'],
        );
    }
}

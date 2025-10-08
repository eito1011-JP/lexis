<?php

namespace App\Dto\UseCase\Document;

use App\Dto\UseCase\UseCaseDto;

/**
 * ドキュメント作成UseCase用DTO
 */
class CreateDocumentUseCaseDto extends UseCaseDto
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly int $categoryEntityId,
        public readonly object $user
    ) {}

    /**
     * リクエストデータからDTOを作成
     */
    public static function fromRequest(array $requestData, object $user): self
    {
        return new self(
            title: $requestData['title'],
            description: $requestData['description'],
            categoryEntityId: $requestData['category_entity_id'],
            user: $user
        );
    }
}

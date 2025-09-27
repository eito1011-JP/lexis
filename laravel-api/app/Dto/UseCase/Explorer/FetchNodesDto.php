<?php

namespace App\Dto\UseCase\Explorer;

use App\Dto\UseCase\UseCaseDto;

class FetchNodesDto extends UseCaseDto
{
    public function __construct(
        public readonly int $categoryEntityId,
        public readonly ?string $pullRequestEditSessionToken = null,
    ) {}

    /**
     * リクエストデータからDTOを作成
     */
    public static function fromRequest(array $requestData): self
    {
        return new self(
            categoryId: $requestData['category_entity_id'],
            pullRequestEditSessionToken: $requestData['pull_request_edit_session_token'] ?? null,
        );
    }
}

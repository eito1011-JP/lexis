<?php

namespace App\Dto\UseCase\DocumentCategory;

use App\Dto\UseCase\UseCaseDto;

class FetchCategoriesDto extends UseCaseDto
{
    public function __construct(
        public readonly ?int $parentId = null,
        public readonly ?string $pullRequestEditSessionToken = null,
    ) {
    }


    /**
     * リクエストデータからDTOを作成
     */
    public static function fromRequest(array $requestData): self
    {
        return new self(
            parentId: $requestData['parent_id'] ?? null,
            pullRequestEditSessionToken: $requestData['pull_request_edit_session_token'] ?? null,
        );
    }
}

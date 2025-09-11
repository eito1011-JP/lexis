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
     * リクエストからDTOを生成
     */
    public static function fromArray(array $data): self
    {
        return new self(
            parentId: $data['parent_id'] ?? null,
            pullRequestEditSessionToken: $data['pull_request_edit_session_token'] ?? null,
        );
    }
}

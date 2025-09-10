<?php

namespace App\Dto\UseCase\DocumentCategory;

use App\Dto\UseCase\UseCaseDto;

class CreateDocumentCategoryDto extends UseCaseDto
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $parentId,
        public readonly ?int $editPullRequestId,
        public readonly ?string $pullRequestEditToken
    ) {}

    /**
     * リクエストデータからDTOを作成
     */
    public static function fromRequest(array $requestData): self
    {
        return new self(
            title: $requestData['title'],
            description: $requestData['description'],
            parentId: $requestData['parent_id'] ?? null,
            editPullRequestId: $requestData['edit_pull_request_id'],
            pullRequestEditToken: $requestData['pull_request_edit_token'] ?? null
        );
    }
}

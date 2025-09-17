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
        public readonly int $categoryId,
        public readonly ?int $editPullRequestId,
        public readonly ?string $pullRequestEditToken,
        public readonly object $user
    ) {}

    /**
     * リクエストデータからDTOを作成
     *
     * @param array $requestData
     * @param object $user
     * @return self
     */
    public static function fromRequest(array $requestData, object $user): self
    {
        return new self(
            title: $requestData['title'],
            description: $requestData['description'],
            categoryId: $requestData['category_id'],
            editPullRequestId: $requestData['edit_pull_request_id'] ?? null,
            pullRequestEditToken: $requestData['pull_request_edit_token'] ?? null,
            user: $user
        );
    }
}

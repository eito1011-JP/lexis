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
        public readonly int $categoryId,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?int $editPullRequestId = null,
        public readonly ?string $pullRequestEditToken = null,
    ) {}

    /**
     * リクエストからDTOを作成
     */
    public static function fromRequest(array $requestData): self
    {
        return new self(
            categoryId: $requestData['category_id'],
            title: $requestData['title'],
            description: $requestData['description'] ?? null,
            editPullRequestId: $requestData['edit_pull_request_id'] ?? null,
            pullRequestEditToken: $requestData['pull_request_edit_token'] ?? null,
        );
    }
}

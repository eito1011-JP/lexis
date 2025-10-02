<?php

declare(strict_types=1);

namespace App\Dto\UseCase\DocumentCategory;

use App\Dto\UseCase\UseCaseDto;

/**
 * カテゴリ削除のDTOクラス
 */
final class DestroyCategoryEntityDto extends UseCaseDto
{
    public function __construct(
        public readonly int $categoryEntityId,
        public readonly ?int $editPullRequestId = null,
        public readonly ?string $pullRequestEditToken = null,
    ) {}

    /**
     * リクエストデータからDTOを作成
     */
    public static function fromRequest(array $requestData): self
    {
        return new self(
            categoryEntityId: $requestData['category_entity_id'],
            editPullRequestId: $requestData['edit_pull_request_id'] ?? null,
            pullRequestEditToken: $requestData['pull_request_edit_token'] ?? null,
        );
    }
}


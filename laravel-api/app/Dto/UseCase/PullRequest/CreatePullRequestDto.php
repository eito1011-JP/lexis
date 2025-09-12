<?php

declare(strict_types=1);

namespace App\Dto\UseCase\PullRequest;

use App\Dto\UseCase\UseCaseDto;

/**
 * プルリクエスト作成DTO
 */
class CreatePullRequestDto extends UseCaseDto
{
    /**
     * @param string $title プルリクエストのタイトル
     * @param array $diffItems 差分アイテム配列 [['id' => int, 'type' => 'document'|'category'], ...]
     * @param string|null $description プルリクエストの説明
     * @param array|null $reviewers レビュアーのメールアドレス配列
     */
    public function __construct(
        public readonly string $title,
        public readonly array $diffItems,
        public readonly ?string $description = null,
        public readonly ?array $reviewers = null,
    ) {}
}

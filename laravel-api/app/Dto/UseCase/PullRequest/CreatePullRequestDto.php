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
     * @param  int  $userBranchId  ユーザーブランチID
     * @param  int  $organizationId  組織ID
     * @param  string  $title  プルリクエストのタイトル
     * @param  string|null  $description  プルリクエストの説明
     * @param  array|null  $reviewers  レビュアーのメールアドレス配列
     */
    public function __construct(
        public readonly int $userBranchId,
        public readonly int $organizationId,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?array $reviewers = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Dto\UseCase\UserBranchSession;

use App\Dto\UseCase\UseCaseDto;
use App\Models\User;

/**
 * ユーザーブランチセッション作成DTO
 */
class StoreDto extends UseCaseDto
{
    /**
     * @param int $pullRequestId プルリクエストID
     * @param User $user 認証ユーザー
     */
    public function __construct(
        public readonly int $pullRequestId,
        public readonly User $user,
    ) {}
}


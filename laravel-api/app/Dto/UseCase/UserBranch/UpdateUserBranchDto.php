<?php

namespace App\Dto\UseCase\UserBranch;

use App\Models\User;

/**
 * ユーザーブランチ更新のDTO
 */
class UpdateUserBranchDto
{
    /**
     * コンストラクタ
     *
     * @param int $userBranchId ユーザーブランチID
     * @param bool $isActive アクティブ状態
     * @param User $user 認証済みユーザー
     */
    public function __construct(
        public readonly int $userBranchId,
        public readonly bool $isActive,
        public readonly User $user
    ) {
    }
}

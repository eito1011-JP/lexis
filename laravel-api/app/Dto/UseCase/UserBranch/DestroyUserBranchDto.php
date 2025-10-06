<?php

namespace App\Dto\UseCase\UserBranch;

use App\Models\User;

/**
 * ユーザーブランチ削除のDTO
 */
class DestroyUserBranchDto
{
    /**
     * コンストラクタ
     *
     * @param int $userBranchId ユーザーブランチID
     * @param User $user 認証済みユーザー
     */
    public function __construct(
        public readonly int $userBranchId,
        public readonly User $user
    ) {
    }
}

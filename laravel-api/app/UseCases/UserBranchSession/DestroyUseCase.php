<?php

namespace App\UseCases\UserBranchSession;

use App\Dto\UseCase\UserBranchSession\DestroyDto;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ユーザーブランチセッション削除のユースケース
 */
class DestroyUseCase
{
    /**
     * ユーザーブランチセッションを削除
     *
     * @param DestroyDto $dto DTO
     * @return void
     *
     * @throws NotFoundException ユーザーブランチが見つからない場合
     */
    public function execute(DestroyDto $dto): void
    {
        try {
            $organizationId = $dto->user->organizationMember?->organization_id;

            if (! $organizationId) {
                throw new NotFoundException();
            }

            // 指定されたユーザーブランチを取得
            $userBranch = UserBranch::where('id', $dto->userBranchId)
                ->where('organization_id', $organizationId)
                ->first();

            if (! $userBranch) {
                throw new NotFoundException();
            }

            UserBranchSession::where('user_branch_id', $userBranch->id)
                ->where('user_id', $dto->user->id)
                ->delete();
        } catch (Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}

<?php

namespace App\UseCases\UserBranch;

use App\Dto\UseCase\UserBranch\UpdateUserBranchDto;
use App\Models\UserBranch;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ユーザーブランチ更新のユースケース
 */
class UpdateUserBranchUseCase
{
    /**
     * ユーザーブランチのis_activeを更新
     *
     * @param UpdateUserBranchDto $dto DTO
     * @return UserBranch 更新結果
     *
     * @throws NotFoundException ユーザーブランチが見つからない場合
     */
    public function execute(UpdateUserBranchDto $dto): UserBranch
    {
        try {
            $organizationId = $dto->user->organizationMember->organization_id;

            if (! $organizationId) {
                throw new NotFoundException();
            }

            // 指定されたユーザーブランチを取得
            $userBranch = UserBranch::where('id', $dto->userBranchId)
                ->where('user_id', $dto->user->id)
                ->where('organization_id', $organizationId)
                ->first();

            if (! $userBranch) {
                throw new NotFoundException();
            }

            // is_activeを更新
            $userBranch->update(['is_active' => $dto->isActive]);
            $userBranch->refresh();

            return $userBranch;
        } catch (Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}

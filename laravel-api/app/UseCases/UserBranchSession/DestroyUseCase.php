<?php

namespace App\UseCases\UserBranchSession;

use App\Dto\UseCase\UserBranchSession\DestroyDto;
use App\Models\UserBranch;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ユーザーブランチセッション削除のユースケース
 */
class DestroyUseCase
{
    /**
     * @param UserBranchService $userBranchService
     */
    public function __construct(
        private UserBranchService $userBranchService
    ) {
    }

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

            $this->userBranchService->deleteUserBranchSessions($userBranch, $dto->user->id);
        } catch (Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}

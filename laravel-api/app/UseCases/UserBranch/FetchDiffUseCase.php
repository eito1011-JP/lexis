<?php

namespace App\UseCases\UserBranch;

use App\Models\User;
use App\Services\DocumentDiffService;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;

class FetchDiffUseCase
{
    public function __construct(
        private DocumentDiffService $documentDiffService,
        private UserBranchService $userBranchService
    ) {}

    /**
     * ユーザーブランチの差分データを取得
     *
     * @throws NotFoundException
     */
    public function execute(User $user, int $userBranchId): array
    {
        try {
            // アクティブなユーザーブランチを取得
            $organizationId = $user->organizationMember->organization_id;
            $userBranch = $this->userBranchService->findActiveUserBranch(
                $userBranchId,
                $organizationId,
                $user->id
            );

            if (! $userBranch) {
                throw new NotFoundException();
            }

            // ユーザーブランチと関連データを一括取得
            $userBranch->load([
                'editStartVersions',
                'editStartVersions.originalDocumentVersion',
                'editStartVersions.currentDocumentVersion',
                'editStartVersions.originalCategoryVersion',
                'editStartVersions.currentCategoryVersion',
            ]);

            // 差分データを生成
            $diffResult = $this->documentDiffService->generateDiffData($userBranch->editStartVersions);

            $diffResult['user_branch_id'] = $userBranch->id;
            $diffResult['organization_id'] = $userBranch->organization->id;
            
            return $diffResult;
        } catch (\Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}

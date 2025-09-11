<?php

namespace App\UseCases\UserBranch;

use App\Exceptions\TargetDocumentNotFoundException;
use App\Models\User;
use App\Services\DocumentDiffService;
use Illuminate\Support\Facades\Log;

class FetchDiffUseCase
{
    public function __construct(
        private DocumentDiffService $documentDiffService
    ) {}

    /**
     * ユーザーブランチの差分データを取得
     *
     * @param User $user
     * @return array
     * @throws TargetDocumentNotFoundException
     */
    public function execute(User $user): array
    {
        try {
            // ユーザーブランチと関連データを一括取得
            $userBranch = $user->userBranches()->active()
                ->with([
                    'editStartVersions',
                    'editStartVersions.originalDocumentVersion',
                    'editStartVersions.currentDocumentVersion',
                    'editStartVersions.originalCategory',
                    'editStartVersions.currentCategory',
                ])
                ->first();

            if (!$userBranch) {
                throw new TargetDocumentNotFoundException(
                    'アクティブなユーザーブランチが見つかりません',
                    'MSG_USER_BRANCH_NOT_FOUND',
                    404
                );
            }

            // 差分データを生成
            $diffResult = $this->documentDiffService->generateDiffData($userBranch->editStartVersions);

            return $diffResult;

        } catch (TargetDocumentNotFoundException $e) {
            Log::error('ユーザーブランチが見つかりません: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Git差分の取得に失敗しました: ' . $e->getMessage());
            throw $e;
        }
    }
}

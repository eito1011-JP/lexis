<?php

namespace App\UseCases\UserBranch;

use App\Models\User;
use App\Services\DocumentDiffService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;

class FetchDiffUseCase
{
    public function __construct(
        private DocumentDiffService $documentDiffService
    ) {}

    /**
     * ユーザーブランチの差分データを取得
     *
     * @throws NotFoundException
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

            if (! $userBranch) {
                throw new NotFoundException();
            }

            // 差分データを生成
            $diffResult = $this->documentDiffService->generateDiffData($userBranch->editStartVersions);

            $diffResult['user_branch_id'] = $userBranch->id;
            $diffResult['organization_id'] = $userBranch->organization->id;


            // 返すもの
            // 該当のuser_branchで編集されたoriginalとcurrentのドキュメントとカテゴリバージョン
            
            return $diffResult;
        } catch (\Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}

<?php

namespace App\Services;

use App\Consts\Flag;
use App\Enums\DocumentCategoryPrStatus;
use App\Models\UserBranch;
use Github\Client;

class UserBranchService
{
    /**
     * ユーザーのアクティブなブランチを取得または作成する
     *
     * @return int ユーザーブランチID
     */
    public function fetchOrCreateActiveBranch(int $userId): int
    {
        // アクティブなユーザーブランチを確認
        $activeBranch = UserBranch::getActiveBranch($userId);

        $userBranchId = null;
        if ($activeBranch) {
            $userBranchId = $activeBranch->id;
        }

        // アクティブなブランチが存在しない場合は新しく作成
        $newBranch = $this->initBranchSnapshot($userId);

        if ($newBranch) {
            $userBranchId = $newBranch->id;
        }

        return $userBranchId;
    }

    /**
     * ブランチスナップショットを初期化する
     */
    private function initBranchSnapshot(int $userId): UserBranch
    {
        $snapshotCommit = $this->findLatestCommit();

        // 既存のアクティブブランチを非アクティブにする
        UserBranch::where('user_id', $userId)
            ->active()
            ->update(['is_active' => Flag::FALSE]);

        // 新しいブランチを作成
        $branchName = 'branch_'.$userId.'_'.time();

        return UserBranch::create([
            'user_id' => $userId,
            'branch_name' => $branchName,
            'snapshot_commit' => $snapshotCommit,
            'is_active' => Flag::TRUE,
            'pr_status' => DocumentCategoryPrStatus::NONE,
        ]);
    }

    /**
     * 最新のコミットハッシュを取得
     */
    private function findLatestCommit(): string
    {
        $client = new Client;

        $response = $client->gitData()->references()->show(
            config('services.github.owner'),
            config('services.github.repo'),
            'heads/main'
        );

        return $response['object']['sha'];
    }
}

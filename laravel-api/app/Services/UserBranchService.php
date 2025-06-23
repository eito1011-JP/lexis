<?php

namespace App\Services;

use App\Consts\Flag;
use App\Enums\DocumentCategoryPrStatus;
use App\Models\UserBranch;
use Exception;

class UserBranchService
{
    /**
     * ユーザーのアクティブなブランチを取得または作成する
     *
     * @return int ユーザーブランチID
     *
     * @throws Exception
     */
    public function getOrCreateActiveBranch(int $userId, string $email): int
    {
        // アクティブなユーザーブランチを確認
        $activeBranch = UserBranch::getActiveBranch($userId);

        if ($activeBranch) {
            return $activeBranch->id;
        }

        // アクティブなブランチが存在しない場合は新しく作成
        $this->initBranchSnapshot($userId, $email);

        $newBranch = UserBranch::where('user_id', $userId)
            ->active()
            ->orderBy('id', 'desc')
            ->first();

        if (! $newBranch) {
            throw new Exception('ブランチの作成に失敗しました');
        }

        return $newBranch->id;
    }

    /**
     * ブランチスナップショットを初期化する
     */
    private function initBranchSnapshot(int $userId): void
    {
        // 既存のアクティブブランチを非アクティブにする
        UserBranch::where('user_id', $userId)
            ->active()
            ->update(['is_active' => Flag::FALSE]);

        // 新しいブランチを作成
        $branchName = 'branch_'.$userId.'_'.time();

        UserBranch::create([
            'user_id' => $userId,
            'branch_name' => $branchName,
            'is_active' => Flag::TRUE,
            'pr_status' => DocumentCategoryPrStatus::NONE,
        ]);
    }

    /**
     * ユーザーIDとメールアドレスからユーザーブランチIDを取得する
     *
     * @throws Exception
     */
    public function getUserBranchId(int $userId, string $email): int
    {
        return $this->getOrCreateActiveBranch($userId, $email);
    }
}

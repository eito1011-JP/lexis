<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserBranchSession;
use Illuminate\Support\Facades\DB;

class UserBranchService extends BaseService
{
    /**
     * ユーザーのアクティブなブランチを取得または作成する
     *
     * @param  User  $user  ユーザー
     * @param  int  $organizationId  組織ID
     * @return int ユーザーブランチID
     */
    public function fetchOrCreateActiveBranch(User $user, int $organizationId): int
    {
        // アクティブなユーザーブランチセッションを確認（リレーション経由）
        $activeUserBranchSession = $user->userBranchSessions()
            ->with(['userBranch' => function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            }])
            ->orderByCreatedAtDesc()
            ->first();

        // アクティブなセッションが存在し、組織IDが一致する場合はそのブランチIDを返す
        if ($activeUserBranchSession && $activeUserBranchSession->userBranch) {
            return $activeUserBranchSession->userBranch->id;
        }

        // アクティブなブランチが存在しない場合は新しいブランチを作成
        return $this->initBranchSnapshot($user->id, $organizationId)->id;
    }

    /**
     * ブランチスナップショットを初期化する
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $organizationId  組織ID
     * @return UserBranch 作成されたユーザーブランチ
     */
    private function initBranchSnapshot(int $userId, int $organizationId): UserBranch
    {
        return DB::transaction(function () use ($userId, $organizationId) {
            // 新しいブランチを作成
            $branchName = 'branch_'.$userId.'_'.time();

            $userBranch = UserBranch::create([
                'creator_id' => $userId,
                'branch_name' => $branchName,
                'organization_id' => $organizationId,
            ]);

            // アクティブなセッションを作成
            UserBranchSession::create([
                'user_id' => $userId,
                'user_branch_id' => $userBranch->id,
            ]);

            return $userBranch;
        });
    }

    /**
     * ユーザーブランチを取得し、アクティブでない場合は例外をスロー
     *
     * @param  int  $userBranchId  ユーザーブランチID
     * @param  int  $organizationId  組織ID
     * @param  int  $userId  ユーザーID
     * @return UserBranch アクティブなユーザーブランチ
     *
     * @return UserBranch|null ユーザーブランチが見つからない、またはアクティブでない場合
     */
    public function findActiveUserBranch(int $userBranchId, int $organizationId, int $userId): ?UserBranch
    {
        // アクティブなセッションが存在するユーザーブランチを取得
        return UserBranch::with('sessions')
            ->whereHas('sessions', function ($query) use ($userBranchId, $organizationId, $userId) {
                $query->where('organization_id', $organizationId);
                $query->where('user_id', $userId);
                $query->where('user_branch_id', $userBranchId);
            })
            ->first();
    }
}

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
        $activeUserBranchSession = $this->hasUserActiveBranchSession($user, $organizationId);

        // アクティブなセッションが存在し、組織IDが一致する場合はそのブランチIDを返す
        if ($activeUserBranchSession) {
            return $activeUserBranchSession->id;
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
     * ユーザーがアクティブなブランチセッションを持っているか
     *
     * @param  User  $user  ユーザー
     * @param  int  $organizationId  組織ID
     * @return UserBranch|null アクティブなユーザーブランチセッションを持っているか検証。存在しない場合はnull
     */
    public function hasUserActiveBranchSession(User $user, int $organizationId): ?UserBranch
    {
        $activeUserBranchSession = $user->userBranchSessions()
            ->with(['userBranch' => function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            }])
            ->first();

        return $activeUserBranchSession?->userBranch;
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
        return UserBranch::with('userBranchSessions')
            ->where('id', $userBranchId)
            ->where('organization_id', $organizationId)
            ->whereHas('userBranchSessions', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->first();
    }

    /**
     * 指定されたユーザーブランチのユーザーブランチセッションを削除
     *
     * @param  UserBranch  $userBranch  ユーザーブランチ
     * @param  int  $userId  ユーザーID
     * @return void
     */
    public function deleteUserBranchSessions(UserBranch $userBranch, int $userId): void
    {
        UserBranchSession::where('user_branch_id', $userBranch->id)
            ->where('user_id', $userId)
            ->delete();
    }
}

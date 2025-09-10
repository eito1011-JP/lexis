<?php

namespace App\Services;

use App\Consts\Flag;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use Github\Client;
use Illuminate\Support\Facades\Log;

class UserBranchService
{
    public function __construct(
        private Client $githubClient
    ) {}

    /**
     * ユーザーのアクティブなブランチを取得または作成する
     *
     * @param  User  $user  ユーザー
     * @param  int|null  $editPullRequestId  編集対象のプルリクエストID
     * @return int ユーザーブランチID
     */
    public function fetchOrCreateActiveBranch(User $user, int $organizationId, ?int $editPullRequestId = null): int
    {
        // プルリクエストが指定されている場合は、そのプルリクエストのブランチを使用
        if ($editPullRequestId) {
            $pullRequest = PullRequest::organization($organizationId)->findOrFail($editPullRequestId);

            return $pullRequest->user_branch_id;
        }

        // アクティブなユーザーブランチを確認（リレーション経由）
        $activeBranch = $user->userBranches()
            ->where('organization_id', $organizationId)
            ->with('organization')
            ->active()
            ->orderByCreatedAtDesc()
            ->first();

        // アクティブなブランチが存在する場合はそのIDを返す
        if ($activeBranch) {
            return $activeBranch->id;
        }

        // アクティブなブランチが存在しない場合は新しいブランチを作成
        return $this->initBranchSnapshot($user->id, $organizationId)->id;
    }

    /**
     * ブランチスナップショットを初期化する
     */
    private function initBranchSnapshot(int $userId, int $organizationId): UserBranch
    {
        // 新しいブランチを作成
        $branchName = 'branch_'.$userId.'_'.time();

        return UserBranch::create([
            'user_id' => $userId,
            'branch_name' => $branchName,
            'is_active' => Flag::TRUE,
            'organization_id' => $organizationId,
        ]);
    }
}

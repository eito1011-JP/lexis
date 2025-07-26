<?php

namespace App\Services;

use App\Consts\Flag;
use App\Models\PullRequest;
use App\Models\User;
use App\Models\UserBranch;
use Github\Client;

class UserBranchService
{
    /**
     * ユーザーのアクティブなブランチを取得または作成する
     *
     * @param  User  $user  ユーザー
     * @param  int|null  $editPullRequestId  編集対象のプルリクエストID
     * @return int ユーザーブランチID
     */
    public function fetchOrCreateActiveBranch(User $user, ?int $editPullRequestId = null): int
    {
        // プルリクエストが指定されている場合は、そのプルリクエストのブランチを使用
        if ($editPullRequestId) {
            $pullRequest = PullRequest::findOrFail($editPullRequestId);

            return $pullRequest->user_branch_id;
        }

        // アクティブなユーザーブランチを確認
        $activeBranch = $user->userBranches()->active()->first();

        // アクティブなブランチが存在する場合はそのIDを返す
        if ($activeBranch) {
            return $activeBranch->id;
        }

        // アクティブなブランチが存在しない場合は新しいブランチを作成
        return $this->initBranchSnapshot($user->id)->id;
    }

    /**
     * ブランチスナップショットを初期化する
     */
    private function initBranchSnapshot(int $userId): UserBranch
    {
        $snapshotCommit = $this->findLatestCommit();

        // 新しいブランチを作成
        $branchName = 'branch_'.$userId.'_'.time();

        return UserBranch::create([
            'user_id' => $userId,
            'branch_name' => $branchName,
            'snapshot_commit' => $snapshotCommit,
            'is_active' => Flag::TRUE,
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

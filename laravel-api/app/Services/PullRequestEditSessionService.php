<?php

namespace App\Services;

use App\Models\PullRequestEditSession;

class PullRequestEditSessionService
{
    /**
     * プルリクエスト編集セッションIDを取得
     *
     * @param  int  $editPullRequestId  編集プルリクエストID
     * @param  string  $pullRequestEditToken  プルリクエスト編集トークン
     * @param  int  $userId  ユーザーID
     */
    public function getPullRequestEditSessionId(int $editPullRequestId, string $pullRequestEditToken, int $userId): ?int
    {
        return PullRequestEditSession::where('pull_request_id', $editPullRequestId)
            ->where('edit_token', $pullRequestEditToken)
            ->whereNull('finished_at')
            ->where('user_id', $userId)
            ->value('id');
    }
}

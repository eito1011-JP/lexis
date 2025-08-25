<?php

namespace App\Repositories\Interfaces;

use App\Models\PullRequestEditSession;

/**
 * プルリクエスト編集セッションRepositoryのインターフェース
 */
interface PullRequestEditSessionRepositoryInterface
{
    /**
     * 有効な編集セッションを検索
     *
     * @param  int  $pullRequestId  プルリクエストID
     * @param  string  $token  編集トークン
     * @param  int  $userId  ユーザーID
     * @return PullRequestEditSession|null 編集セッション
     */
    public function findValidSession(int $pullRequestId, string $token, int $userId): ?PullRequestEditSession;

    /**
     * プルリクエスト編集セッションIDを取得
     *
     * @param  int  $pullRequestId  プルリクエストID
     * @param  string  $token  編集トークン
     * @param  int  $userId  ユーザーID
     * @return int|null 編集セッションID
     */
    public function findEditSessionId(int $pullRequestId, string $token, int $userId): ?int;
}

<?php

namespace App\Repositories;

use App\Models\PullRequestEditSession;
use App\Repositories\Interfaces\PullRequestEditSessionRepositoryInterface;

/**
 * プルリクエスト編集セッションRepository
 *
 * PullRequestEditSessionモデルのデータアクセス層を担当
 */
class PullRequestEditSessionRepository implements PullRequestEditSessionRepositoryInterface
{
    /**
     * コンストラクタ
     *
     * @param  PullRequestEditSession  $model  プルリクエスト編集セッションモデル
     */
    public function __construct(
        private PullRequestEditSession $model
    ) {}

    /**
     * 有効な編集セッションを検索
     *
     * @param  int  $pullRequestId  プルリクエストID
     * @param  string  $token  編集トークン
     * @param  int  $userId  ユーザーID
     * @return PullRequestEditSession|null 編集セッション
     */
    public function findValidSession(int $pullRequestId, string $token, int $userId): ?PullRequestEditSession
    {
        return $this->model->byPullRequest($pullRequestId)
            ->byToken($token)
            ->byUser($userId)
            ->active()
            ->first();
    }

    /**
     * プルリクエスト編集セッションIDを取得
     *
     * @param  int  $pullRequestId  プルリクエストID
     * @param  string  $token  編集トークン
     * @param  int  $userId  ユーザーID
     * @return int|null 編集セッションID
     */
    public function findEditSessionId(int $pullRequestId, string $token, int $userId): ?int
    {
        return $this->model->byPullRequest($pullRequestId)
            ->byToken($token)
            ->byUser($userId)
            ->active()
            ->value('id');
    }
}

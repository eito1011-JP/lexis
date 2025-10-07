<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PullRequestActivityAction;
use App\Models\ActivityLogOnPullRequest;
use App\Models\PullRequest;
use App\Models\User;

/**
 * プルリクエストアクティビティログサービス
 */
class PullRequestActivityLogService extends BaseService
{
    /**
     * プルリクエストタイトル編集のアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  string  $newTitle  新しいタイトル
     * @return void
     */
    public function createTitleEditLog(User $user, PullRequest $pullRequest, string $newTitle): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::PULL_REQUEST_TITLE_EDITED->value,
            'old_pull_request_title' => $pullRequest->title,
            'new_pull_request_title' => $newTitle,
        ]);
    }

    /**
     * プルリクエストマージのアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @return void
     */
    public function createMergeLog(User $user, PullRequest $pullRequest): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::PULL_REQUEST_MERGED->value,
        ]);
    }

    /**
     * プルリクエストクローズのアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @return void
     */
    public function createCloseLog(User $user, PullRequest $pullRequest): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::PULL_REQUEST_CLOSED->value,
        ]);
    }

    /**
     * プルリクエスト再オープンのアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @return void
     */
    public function createReopenLog(User $user, PullRequest $pullRequest): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::PULL_REQUEST_REOPENED->value,
        ]);
    }

    /**
     * 修正リクエスト送信のアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  string  $fixRequestToken  修正リクエストトークン
     * @return void
     */
    public function createFixRequestSentLog(User $user, PullRequest $pullRequest, string $fixRequestToken): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::FIX_REQUEST_SENT->value,
            'fix_request_token' => $fixRequestToken,
        ]);
    }

    /**
     * 修正リクエスト適用のアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  string  $fixRequestToken  修正リクエストトークン
     * @return void
     */
    public function createFixRequestAppliedLog(User $user, PullRequest $pullRequest, string $fixRequestToken): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::FIX_REQUEST_APPLIED->value,
            'fix_request_token' => $fixRequestToken,
        ]);
    }

    /**
     * レビュアー設定のアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  int  $reviewerId  レビュアーID
     * @return void
     */
    public function createAssignedReviewerLog(User $user, PullRequest $pullRequest, int $reviewerId): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::ASSIGNED_REVIEWER->value,
            'reviewer_id' => $reviewerId,
        ]);
    }

    /**
     * レビュアー承認のアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  int  $reviewerId  レビュアーID
     * @return void
     */
    public function createReviewerApprovedLog(User $user, PullRequest $pullRequest, int $reviewerId): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::REVIEWER_APPROVED->value,
            'reviewer_id' => $reviewerId,
        ]);
    }

    /**
     * コメント投稿のアクティビティログを作成
     *
     * @param  User  $user  ユーザー
     * @param  PullRequest  $pullRequest  プルリクエスト
     * @param  int  $commentId  コメントID
     * @return void
     */
    public function createCommentedLog(User $user, PullRequest $pullRequest, int $commentId): void
    {
        ActivityLogOnPullRequest::create([
            'user_id' => $user->id,
            'pull_request_id' => $pullRequest->id,
            'action' => PullRequestActivityAction::COMMENTED->value,
            'comment_id' => $commentId,
        ]);
    }
}

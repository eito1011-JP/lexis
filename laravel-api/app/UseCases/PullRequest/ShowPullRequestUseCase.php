<?php

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\ShowDto;
use App\Models\ActivityLogOnPullRequest;
use App\Models\PullRequest;
use App\Models\User;
use App\Services\DocumentDiffService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * プルリクエスト詳細取得UseCase
 */
class ShowPullRequestUseCase
{
    public function __construct(
        private DocumentDiffService $documentDiffService,
    ) {}

    /**
     * プルリクエストの詳細を取得
     *
     * @param  ShowPullRequestDto  $dto  プルリクエスト詳細取得DTO
     * @param  User  $user  認証済みユーザー
     * @return array プルリクエスト詳細データ
     *
     * @throws ModelNotFoundException
     */
    public function execute(ShowDto $dto, User $user): array
    {
        try {
        // 1. プルリクエストを取得（status = opened or conflict）
        $pullRequest = PullRequest::with([
            'userBranch.user',
            'userBranch.editStartVersions',
            'userBranch.editStartVersions.originalDocumentVersion',
            'userBranch.editStartVersions.currentDocumentVersion',
            'userBranch.editStartVersions.originalDocumentVersion.category',
            'userBranch.editStartVersions.currentDocumentVersion.category',
            'reviewers.user',
        ])
            ->where('id', $dto->pullRequestId)
            ->firstOrFail();

        // 2. 差分データを生成
        $diffResult = $this->documentDiffService->generateDiffData($pullRequest->userBranch->editStartVersions);

        // 3. レビュアー情報を取得
        $reviewers = $pullRequest->reviewers->map(function ($reviewer) {
            return [
                'user_id' => $reviewer->user->id,
                'email' => $reviewer->user->email,
                'action_status' => $reviewer->action_status,
            ];
        })->toArray();

        // 5. アクティビティログを取得
        $activityLogs = ActivityLogOnPullRequest::with([
            'user:id,nickname,email',
            'comment:id,content,created_at',
            'fixRequest:id,token,created_at',
            'reviewer:id,nickname,email',
            'pullRequestEditSession:id,token,created_at',
        ])
            ->where('pull_request_id', $dto->pullRequestId)
            ->orderBy('created_at', 'asc')
            ->get();

        // 6. アクティビティログをレスポンス形式に変換
        $activityLogsData = $activityLogs->map(function ($log) {
            return [
                'id' => $log->id,
                'pull_request_id' => $log->pull_request_id,
                'action' => $log->action,
                'actor' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->nickname ?? $log->user->email,
                    'email' => $log->user->email,
                ] : null,
                'comment' => $log->comment ? [
                    'id' => $log->comment->id,
                    'content' => $log->comment->content,
                    'created_at' => $log->comment->created_at->toISOString(),
                ] : null,
                'fix_request' => $log->fixRequest ? [
                    'id' => $log->fixRequest->id,
                    'token' => $log->fixRequest->token,
                    'created_at' => $log->fixRequest->created_at->toISOString(),
                ] : null,
                'pull_request_edit_session' => $log->pullRequestEditSession ? [
                    'id' => $log->pullRequestEditSession->id,
                    'token' => $log->pullRequestEditSession->token,
                    'created_at' => $log->pullRequestEditSession->created_at->toISOString(),
                ] : null,
                'old_pull_request_title' => $log->old_pull_request_title,
                'new_pull_request_title' => $log->new_pull_request_title,
                'fix_request_token' => $log->fix_request_token,
                'created_at' => $log->created_at->toISOString(),
            ];
        })->toArray();

        return [
            ...$diffResult,
            'title' => $pullRequest->title,
            'description' => $pullRequest->description,
            'status' => $pullRequest->status,
            'author_nickname' => $pullRequest->userBranch->user->nickname,
            'author_email' => $pullRequest->userBranch->user->email,
            'reviewers' => $reviewers,
            'created_at' => $pullRequest->created_at,
            'activity_logs' => $activityLogsData,
            ];
        } catch (\Exception $e) {
            Log::error($e);

            throw $e;
        }
    }
}

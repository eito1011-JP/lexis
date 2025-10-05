<?php

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\ShowDto;
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
        Log::info('プルリクエスト詳細取得UseCase'.json_encode($dto));
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

        // 4. プルリクエスト作成者の名前とメールアドレスを取得
        $authorName = $pullRequest->userBranch->user->name ?? null;
        $authorEmail = $pullRequest->userBranch->user->email ?? null;

        return [
            ...$diffResult,
            'title' => $pullRequest->title,
            'description' => $pullRequest->description,
            'status' => $pullRequest->status,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'reviewers' => $reviewers,
                'created_at' => $pullRequest->created_at,
            ];
        } catch (\Exception $e) {
            Log::error($e);

            throw $e;
        }
    }
}

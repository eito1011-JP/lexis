<?php

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\MergePullRequestDto;
use App\Enums\OrganizationRoleBindingRole;
use App\Enums\PullRequestStatus;
use App\Models\PullRequest;
use App\Services\GitService;
use App\Services\PullRequestMergeService;
use Illuminate\Support\Facades\Log;

class MergePullRequestUseCase
{
    protected PullRequestMergeService $pullRequestMergeService;

    protected GitService $gitService;

    public function __construct(
        PullRequestMergeService $pullRequestMergeService,
        GitService $gitService
    ) {
        $this->pullRequestMergeService = $pullRequestMergeService;
        $this->gitService = $gitService;
    }

    /**
     * プルリクエストをマージする
     */
    public function execute(MergePullRequestDto $dto): array
    {
        try {
                        // 2. ログインユーザーのroleがowner or adminであることを確認
            if (! OrganizationRoleBindingRole::from($dto->userId)->isAdmin() && ! OrganizationRoleBindingRole::from($dto->userId)->isOwner()) {
                throw new \Exception('権限がありません');
            }

            // プルリクエストを取得（status = opened）
            $pullRequest = PullRequest::where('id', $dto->pullRequestId)
                ->where('status', PullRequestStatus::OPENED->value)
                ->firstOrFail();

            // mergeable stateをチェック
            $prInfo = $this->gitService->getPullRequestInfo($pullRequest->pr_number);

            // mergeableがfalseの場合はエラー
            if ($prInfo['mergeable'] === false) {
                return [
                    'success' => false,
                    'error' => 'プルリクエストがマージできない状態です。コンフリクトを解決してください。',
                ];
            }

            // マージ処理を実行
            $result = $this->pullRequestMergeService->mergePullRequest($dto->pullRequestId, $dto->userId);

            return $result;

        } catch (\Exception $e) {
            Log::error('プルリクエストマージエラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $dto->pullRequestId,
                'user_id' => $dto->userId,
            ]);

            return [
                'success' => false,
                'error' => 'マージ処理に失敗しました',
            ];
        }
    }
}

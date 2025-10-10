<?php

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\CreatePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\PullRequestStatus;
use App\Models\CategoryVersion;
use App\Models\DocumentVersion;
use App\Models\PullRequest;
use App\Models\PullRequestReviewer;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\CommitService;
use App\Services\OrganizationService;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * プルリクエスト作成UseCase
 */
class CreatePullRequestUseCase
{
    public function __construct(
        private OrganizationService $organizationService,
        private UserBranchService $userBranchService,
        private CommitService $commitService,
    ) {}

    /**
     * プルリクエストを作成
     *
     * @param  CreatePullRequestDto  $dto  プルリクエスト作成DTO
     * @param  User  $user  認証済みユーザー
     * @return PullRequest プルリクエスト作成結果
     *
     * @throws \Exception
     */
    public function execute(CreatePullRequestDto $dto, User $user): PullRequest
    {
        DB::beginTransaction();

        try {
            // 1. ユーザーが組織に所属しているか確認
            $this->organizationService->validateUserBelongsToOrganization($user->id, $dto->organizationId);

            // 2. user_branch_idがactiveか確認
            $userBranch = $this->userBranchService->findActiveUserBranch($dto->userBranchId, $dto->organizationId, $user->id);

            if (! $userBranch) {
                throw new NotFoundException;
            }

            // 3. document_versionsをactiveなuser_branch_idで絞り込んでstatus = pushedにupdate
            $this->updateDocumentVersionStatus($dto->userBranchId);

            // 4. document_categoriesをactiveなuser_branch_idで絞り込んでstatus = pushedにupdate
            $this->updateCategoryVersionStatus($dto->userBranchId);

            // 5. pull_requestテーブルにレコードを作成（status = opened）
            $pullRequest = $this->createPullRequest($dto, $userBranch);

            // 6. 初回コミットを作成（PRのタイトルと同じメッセージ）
            $this->commitService->createCommitFromUserBranch(
                $user,
                $pullRequest,
                $userBranch,
                $pullRequest->title
            );

            // 7. ユーザーブランチセッションを削除
            $this->userBranchService->deleteUserBranchSessions($userBranch, $user->id);

            // 8. レビュアーのuser_idを取得してpull_request_reviewersテーブルに一括insert
            if (! empty($dto->reviewers)) {
                $this->createPullRequestReviewers($pullRequest->id, $dto->reviewers);
            }

            DB::commit();

            return $pullRequest;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw $e;
        }
    }

    /**
     * document_versionsのステータスをpushedに更新
     */
    private function updateDocumentVersionStatus(int $userBranchId): void
    {
        DocumentVersion::where('user_branch_id', $userBranchId)
            ->update(['status' => DocumentStatus::PUSHED->value]);
    }

    /**
     * document_categoriesのステータスをpushedに更新
     */
    private function updateCategoryVersionStatus(int $userBranchId): void
    {
        CategoryVersion::where('user_branch_id', $userBranchId)
            ->update(['status' => DocumentCategoryStatus::PUSHED->value]);
    }

    /**
     * プルリクエストをDBに保存
     */
    private function createPullRequest(CreatePullRequestDto $dto, UserBranch $userBranch): PullRequest
    {
        return PullRequest::create([
            'user_branch_id' => $userBranch->id,
            'organization_id' => $dto->organizationId,
            'title' => $dto->title,
            'description' => $dto->description,
            'status' => PullRequestStatus::OPENED->value,
        ]);
    }

    /**
     * プルリクエストレビュアーをDBに保存
     */
    private function createPullRequestReviewers(int $pullRequestId, array $reviewerEmails): void
    {
        // メールアドレスからuser_idを取得
        $reviewerUsers = User::whereIn('email', $reviewerEmails)->get();

        if ($reviewerUsers->count() !== count($reviewerEmails)) {
            throw new NotFoundException;
        }

        $reviewerData = $reviewerUsers->map(function ($user) use ($pullRequestId) {
            return [
                'pull_request_id' => $pullRequestId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        PullRequestReviewer::insert($reviewerData);
    }
}

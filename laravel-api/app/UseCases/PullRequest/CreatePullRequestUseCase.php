<?php

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\CreatePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\PullRequestStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequest;
use App\Models\PullRequestReviewer;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * プルリクエスト作成UseCase
 */
class CreatePullRequestUseCase
{
    /**
     * プルリクエストを作成
     *
     * @param CreatePullRequestDto $dto プルリクエスト作成DTO
     * @param User $user 認証済みユーザー
     * @return array プルリクエスト作成結果
     * @throws \Exception
     */
    public function execute(CreatePullRequestDto $dto, User $user): array
    {
        Log::info('CreatePullRequestUseCase start', ['dto' => $dto->toArray(), 'user_id' => $user->id]);

        DB::beginTransaction();

        try {
            // 1. ユーザーが組織に所属しているか確認
            $this->validateUserBelongsToOrganization($user, $dto->organizationId);

            // 2. user_branch_idがactiveか確認
            $userBranch = $this->validateUserBranchIsActive($dto->userBranchId);

            // 3. document_versionsをactiveなuser_branch_idで絞り込んでstatus = pushedにupdate
            $this->updateDocumentVersionsStatus($dto->userBranchId);

            // 4. document_categoriesをactiveなuser_branch_idで絞り込んでstatus = pushedにupdate
            $this->updateDocumentCategoriesStatus($dto->userBranchId);

            // 5. pull_requestテーブルにレコードを作成（status = opened）
            $pullRequest = $this->createPullRequest($dto, $userBranch);

            // 6. レビュアーのuser_idを取得してpull_request_reviewersテーブルに一括insert
            if (!empty($dto->reviewers)) {
                $this->createPullRequestReviewers($pullRequest->id, $dto->reviewers);
            }

            DB::commit();

            Log::info('CreatePullRequestUseCase completed successfully', [
                'pull_request_id' => $pullRequest->id,
            ]);

            return [
                'success' => true,
                'message' => 'プルリクエストを作成しました',
                'pull_request_id' => $pullRequest->id,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
    }

    /**
     * ユーザーが組織に所属しているか確認
     */
    private function validateUserBelongsToOrganization(User $user, int $organizationId): void
    {
        // OrganizationMemberリレーションを使って確認
        $organizationMember = $user->organizationMember;
        
        if (!$organizationMember || $organizationMember->organization_id !== $organizationId) {
            throw new \Exception('ユーザーは指定された組織に所属していません');
        }
    }

    /**
     * user_branch_idがactiveか確認
     */
    private function validateUserBranchIsActive(int $userBranchId): UserBranch
    {
        $userBranch = UserBranch::find($userBranchId);

        if (!$userBranch) {
            throw new \Exception('ユーザーブランチが見つかりません');
        }

        if (!$userBranch->is_active) {
            throw new \Exception('指定されたユーザーブランチはアクティブではありません');
        }

        return $userBranch;
    }

    /**
     * document_versionsのステータスをpushedに更新
     */
    private function updateDocumentVersionsStatus(int $userBranchId): void
    {
        DocumentVersion::where('user_branch_id', $userBranchId)
            ->update(['status' => DocumentStatus::PUSHED->value]);
    }

    /**
     * document_categoriesのステータスをpushedに更新
     */
    private function updateDocumentCategoriesStatus(int $userBranchId): void
    {
        DocumentCategory::where('user_branch_id', $userBranchId)
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
            throw new \Exception('一部のレビュアーが見つかりません');
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
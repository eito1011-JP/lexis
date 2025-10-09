<?php

namespace App\UseCases\Commit;

use App\Dto\UseCase\Commit\CreateCommitDto;
use App\Enums\PullRequestStatus;
use App\Models\Commit;
use App\Models\PullRequest;
use App\Models\User;
use App\Services\CommitService;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * コミット作成UseCase
 */
class CreateCommitUseCase
{
    public function __construct(
        private CommitService $commitService,
        private UserBranchService $userBranchService,
    ) {}

    /**
     * コミットを作成
     *
     * @param  CreateCommitDto  $dto  コミット作成DTO
     * @param  User  $user  認証済みユーザー
     * @return Commit コミット
     *
     * @throws \Exception
     */
    public function execute(CreateCommitDto $dto, User $user): Commit
    {
        DB::beginTransaction();

        try {
            // 1. organization特定
            $organizationId = $user->organizationMember->organization_id;

            if (! $organizationId) {
                throw new NotFoundException();
            }

            // 2. status = openedなPR特定（user_branchも一緒に取得）
            $pullRequest = PullRequest::with('userBranch')
                ->where('id', $dto->pullRequestId)
                ->where('organization_id', $organizationId)
                ->where('status', PullRequestStatus::OPENED->value)
                ->first();

            if (! $pullRequest) {
                throw new NotFoundException();
            }

            // 3. user_branchを取得してアクティブか確認
            $activeUserBranch = $this->userBranchService->findActiveUserBranch($pullRequest->user_branch_id, $organizationId, $user->id);

            if (! $activeUserBranch) {
                throw new NotFoundException();
            }

            // 4. コミット作成処理（EditStartVersionsの取得と更新を含む）
            $commit = $this->commitService->createCommitFromUserBranch(
                $user,
                $pullRequest,
                $activeUserBranch,
                $dto->message
            );

            DB::commit();

            return $commit;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
    }
}

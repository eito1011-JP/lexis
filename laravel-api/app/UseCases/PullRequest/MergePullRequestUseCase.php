<?php

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\MergePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\PullRequestActivityAction;
use App\Enums\PullRequestStatus;
use App\Models\ActivityLogOnPullRequest;
use App\Models\PullRequest;
use App\Policies\PullRequestPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MergePullRequestUseCase
{
    protected PullRequestPolicy $pullRequestPolicy;

    public function __construct(
        PullRequestPolicy $pullRequestPolicy
    ) {
        $this->pullRequestPolicy = $pullRequestPolicy;
    }

    /**
     * プルリクエストをマージする
     */
    public function execute(MergePullRequestDto $dto): array
    {
        DB::beginTransaction();

        try {
            // 1. プルリクエストを取得（status = opened and id = request.pull_request_id）
            // 紐づくuser_branchもloadで取得し、同一ユーザーが操作するのをlockかける
            $pullRequest = PullRequest::with(['userBranch'])
                ->where('id', $dto->pullRequestId)
                ->where('status', PullRequestStatus::OPENED->value)
                ->lockForUpdate()
                ->first();

            if (!$pullRequest) {
                throw new NotFoundException();
            }

            // 3. 操作ユーザーがそのorganizationでadminかowner以上であることを確認（policyでmerge権限実装）
            if (!$this->pullRequestPolicy->merge($dto->userId, $pullRequest)) {
                throw new AuthorizationException();
            }

            // 4. pull_requestに紐づくdocument_versionsとdocument_categoriesのstatusをmergedに更新
            $userBranch = $pullRequest->userBranch;

            // DocumentVersionsのstatusを更新
            $userBranch->documentVersions()->update([
                'status' => DocumentStatus::MERGED->value,
            ]);

            // DocumentCategoriesのstatusを更新
            $userBranch->documentCategories()->update([
                'status' => DocumentCategoryStatus::MERGED->value,
            ]);

            // 5. pull_requestsレコードをmergedにstatus更新
            $pullRequest->update([
                'status' => PullRequestStatus::MERGED->value,
            ]);

            // 6. action = mergedでactivity logを作成
            ActivityLogOnPullRequest::create([
                'user_id' => $dto->userId,
                'pull_request_id' => $pullRequest->id,
                'action' => PullRequestActivityAction::PULL_REQUEST_MERGED->value,
            ]);

            DB::commit();

            return [
                'pull_request_id' => $pullRequest->id,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw $e;
        }
    }
}

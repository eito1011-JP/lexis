<?php

namespace App\UseCases\Commit;

use App\Dto\UseCase\Commit\CreateCommitDto;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Enums\PullRequestStatus;
use App\Models\CategoryVersion;
use App\Models\Commit;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Models\User;
use App\Services\CommitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * コミット作成UseCase
 */
class CreateCommitUseCase
{
    public function __construct(
        private CommitService $commitService,
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
            $pullRequest = PullRequest::with(['userBranch' => function ($query) {
                $query->active();
            }])
                ->where('id', $dto->pullRequestId)
                ->where('organization_id', $organizationId)
                ->where('status', PullRequestStatus::OPENED->value)
                ->first();

            if (! $pullRequest) {
                throw new NotFoundException();
            }

            // 3. user_branchを取得してアクティブか確認
            $userBranch = $pullRequest->userBranch;

            if (! $userBranch) {
                throw new NotFoundException();
            }

            // 5. 該当user_branchでcommit_id = nullのedit_start_versionsを取得
            $editStartVersions = EditStartVersion::where('user_branch_id', $userBranch->id)
                ->whereNull('commit_id')
                ->get();

            if ($editStartVersions->isEmpty()) {
                throw new NotFoundException();
            }

            // 6. コミット作成処理（Serviceを利用）
            $commit = $this->commitService->createCommit(
                $user,
                $pullRequest,
                $userBranch,
                $editStartVersions,
                $dto->message
            );

            // 7. 取得したedit_start_versionsにcommit idを格納
            EditStartVersion::whereIn('id', $editStartVersions->pluck('id'))
                ->update(['commit_id' => $commit->id]);

            // 8. 取得したedit_start_versionsに紐づくversionsレコードをdraft => pushedにupdate
            $this->updateVersionStatus($editStartVersions);

            DB::commit();

            return $commit;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
    }

    /**
     * バージョンのステータスを更新（draft => pushed）
     */
    private function updateVersionStatus($editStartVersions): void
    {
        $documentVersionIds = [];
        $categoryVersionIds = [];

        foreach ($editStartVersions as $editStartVersion) {
            if ($editStartVersion->target_type === 'document') {
                $documentVersionIds[] = $editStartVersion->current_version_id;
            } elseif ($editStartVersion->target_type === 'category') {
                $categoryVersionIds[] = $editStartVersion->current_version_id;
            }
        }

        if (! empty($documentVersionIds)) {
            DocumentVersion::whereIn('id', $documentVersionIds)
                ->where('status', 'draft')
                ->update(['status' => 'pushed']);
        }

        if (! empty($categoryVersionIds)) {
            CategoryVersion::whereIn('id', $categoryVersionIds)
                ->where('status', 'draft')
                ->update(['status' => 'pushed']);
        }
    }
}

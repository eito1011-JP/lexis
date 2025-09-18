<?php

namespace App\UseCases\PullRequest;

use App\Dto\UseCase\PullRequest\MergePullRequestDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestActivityAction;
use App\Enums\PullRequestStatus;
use App\Models\ActivityLogOnPullRequest;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Policies\PullRequestPolicy;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
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

            if (! $pullRequest) {
                throw new NotFoundException;
            }

            // 2. 基本的な権限チェック（組織メンバーかどうか）
            if (! $this->pullRequestPolicy->merge($dto->userId, $pullRequest)) {
                throw new AuthorizationException;
            }

            // 3. 競合解決：同じオリジナルを編集した他のバージョンを論理削除
            $this->resolveConflicts($pullRequest);

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

    /**
     * 競合解決：変更対象となったoriginalのdocument/categoryを論理削除
     *
     * @param  PullRequest  $pullRequest  マージするプルリクエスト
     */
    private function resolveConflicts(PullRequest $pullRequest): void
    {
        // このプルリクエストのブランチに含まれるEditStartVersionを取得
        $editStartVersions = EditStartVersion::where('user_branch_id', $pullRequest->user_branch_id)
            ->get();

        if ($editStartVersions->isEmpty()) {
            return;
        }

        // 競合があるoriginal_version_idとtarget_typeの組み合わせを一括で取得
        $originalVersionIds = $editStartVersions->pluck('original_version_id')->unique();
        $targetTypes = $editStartVersions->pluck('target_type')->unique();

        $conflictingEditStartVersions = EditStartVersion::whereIn('original_version_id', $originalVersionIds)
            ->whereIn('target_type', $targetTypes)
            ->where('user_branch_id', '!=', $pullRequest->user_branch_id)
            ->get()
            ->groupBy(['original_version_id', 'target_type']);

        // 競合があるEditStartVersionのみを抽出
        $conflictingEditStartVersionsList = $editStartVersions->filter(function ($editStartVersion) use ($conflictingEditStartVersions) {
            return isset($conflictingEditStartVersions[$editStartVersion->original_version_id][$editStartVersion->target_type]);
        });

        if ($conflictingEditStartVersionsList->isNotEmpty()) {
            $this->deleteOriginalVersionsBatch($conflictingEditStartVersionsList);
        }
    }

    /**
     * 競合があるoriginalのdocument/categoryを一括で論理削除
     *
     * @param  \Illuminate\Support\Collection  $conflictingEditStartVersions  競合があるEditStartVersionのコレクション
     */
    private function deleteOriginalVersionsBatch(\Illuminate\Support\Collection $conflictingEditStartVersions): void
    {
        // ドキュメントとカテゴリを分離
        $documentEditStartVersions = $conflictingEditStartVersions->filter(function ($editStartVersion) {
            return $editStartVersion->target_type === EditStartVersionTargetType::DOCUMENT->value;
        });

        $categoryEditStartVersions = $conflictingEditStartVersions->filter(function ($editStartVersion) {
            return $editStartVersion->target_type === EditStartVersionTargetType::CATEGORY->value;
        });

        // ドキュメントバージョンを一括論理削除
        if ($documentEditStartVersions->isNotEmpty()) {
            $documentIds = $documentEditStartVersions->pluck('original_version_id')->unique();
            DB::table('document_versions')
                ->whereIn('id', $documentIds)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        // ドキュメントカテゴリを一括論理削除
        if ($categoryEditStartVersions->isNotEmpty()) {
            $categoryIds = $categoryEditStartVersions->pluck('original_version_id')->unique();
            DB::table('document_categories')
                ->whereIn('id', $categoryIds)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        // 対象となったoriginalのEditStartVersionを一括論理削除
        $originalVersionIds = $conflictingEditStartVersions->pluck('original_version_id')->unique();
        $targetTypes = $conflictingEditStartVersions->pluck('target_type')->unique();

        EditStartVersion::whereIn('original_version_id', $originalVersionIds)
            ->whereIn('target_type', $targetTypes)
            ->delete();
    }
}

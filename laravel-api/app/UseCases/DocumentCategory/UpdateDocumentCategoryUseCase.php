<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\UpdateDocumentCategoryDto;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * カテゴリ更新のユースケース
 */
class UpdateDocumentCategoryUseCase
{
    public function __construct(
        private UserBranchService $userBranchService,
    ) {}

    /**
     * ドキュメントカテゴリを更新
     *
     * @param UpdateDocumentCategoryDto $dto カテゴリ更新DTO
     * @param User $user 認証済みユーザー
     * @return DocumentCategory 更新されたカテゴリ
     * @throws NotFoundException 組織またはカテゴリが見つからない場合
     */
    public function execute(UpdateDocumentCategoryDto $dto, User $user): DocumentCategory
    {
        try {
            DB::beginTransaction();

            // 1. $organizationId = $user->organizationMember->organization_id;
            $organizationId = $user->organizationMember->organization_id;

            // 2. if $organizationない場合 throw new NotFoundException;
            if (!$organizationId) {
                throw new NotFoundException();
            }

            // 3. fetchOrCreateActiveBranch
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $organizationId,
                $dto->editPullRequestId
            );

            // 4. PullRequestEditSession::findEditSessionId
            $pullRequestEditSessionId = PullRequestEditSession::findEditSessionId(
                $dto->editPullRequestId,
                null, // pull_request_edit_tokenは今回不要
                $user->id
            );

            // 5. 編集対象のexisitingCategoryを取得
            $existingCategory = DocumentCategory::find($dto->categoryId);

            // 6. if existingCategoryがない場合 throw new NotFoundException;
            if (!$existingCategory) {
                throw new NotFoundException();
            }

            // 7. DocumentCategoryを作成
            $newCategory = DocumentCategory::create([
                'sidebar_label' => $dto->title,
                'slug' => $existingCategory->slug, // 既存のslugを維持
                'parent_id' => $existingCategory->parent_id,
                'position' => $existingCategory->position,
                'description' => $dto->description,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
            ]);

            // 8. EditStartVersionを作成(original_version_id = existingCategory.id, current_version_id = 新規のDocumentCategory.id)
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::CATEGORY->value,
                'original_version_id' => $existingCategory->id,
                'current_version_id' => $newCategory->id,
            ]);

            // 9. プルリクエストを編集している処理を考慮
            if ($pullRequestEditSessionId) {
                PullRequestEditSessionDiff::updateOrCreate(
                    [
                        'pull_request_edit_session_id' => $pullRequestEditSessionId,
                        'target_type' => EditStartVersionTargetType::CATEGORY->value,
                        'current_version_id' => $existingCategory->id,
                    ],
                    [
                        'current_version_id' => $newCategory->id,
                        'diff_type' => 'updated',
                    ]
                );
            }

            DB::commit();
            Log::info('カテゴリを正常に更新しました', ['category_id' => $newCategory->id]);

            return $newCategory;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('カテゴリの更新に失敗しました', [
                'error' => $e->getMessage(),
                'category_id' => $dto->categoryId,
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }
}

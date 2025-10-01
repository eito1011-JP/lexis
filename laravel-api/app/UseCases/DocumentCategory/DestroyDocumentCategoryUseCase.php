<?php

namespace App\UseCases\DocumentCategory;

use App\Consts\Flag;
use App\Dto\UseCase\DocumentCategory\DestroyDocumentCategoryDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Services\DocumentCategoryService;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * カテゴリ削除のユースケース
 */
class DestroyDocumentCategoryUseCase
{
    public function __construct(
        private UserBranchService $userBranchService,
        private DocumentCategoryService $documentCategoryService,
    ) {}

    /**
     * カテゴリを削除
     *
     * @param  DestroyDocumentCategoryDto  $dto  カテゴリ削除DTO
     * @param  User  $user  認証済みユーザー
     * @return array 削除されたドキュメントとカテゴリのバージョンデータ
     *
     * @throws NotFoundException カテゴリが見つからない場合
     */
    public function execute(DestroyDocumentCategoryDto $dto, User $user): array
    {
        try {
            DB::beginTransaction();

            // 1. 組織メンバー確認
            if (! $user->organizationMember) {
                throw new NotFoundException;
            }

            $organizationId = $user->organizationMember->organization_id;

            // 2. 組織が存在しない場合はエラー
            if (! $organizationId) {
                throw new NotFoundException;
            }

            // 3. CategoryEntityの存在確認
            $categoryEntity = CategoryEntity::find($dto->categoryEntityId);

            if (! $categoryEntity) {
                throw new NotFoundException;
            }

            // 4. fetchOrCreateActiveBranch
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $organizationId,
                $dto->editPullRequestId
            );

            // 5. PullRequestEditSession::findEditSessionId
            $pullRequestEditSessionId = PullRequestEditSession::findEditSessionId(
                $dto->editPullRequestId,
                $dto->pullRequestEditToken,
                $user->id
            );

            // 6. 編集対象のexistingCategoryを取得
            $existingCategory = $this->documentCategoryService->getCategoryByWorkContext(
                $dto->categoryEntityId,
                $user,
                $dto->pullRequestEditToken
            );

            if (! $existingCategory) {
                throw new NotFoundException;
            }

            $deletedDocumentVersions = [];
            $deletedCategoryVersions = [];

            // 7. category_entity_idでwhereしたdocを元にis_deleted = 1なdocument_versions, edit_start_versionsを生成
            $documents = DocumentVersion::where('category_entity_id', $dto->categoryEntityId)
                ->get();

            foreach ($documents as $document) {
                // DocumentVersionを作成（削除用）
                $newDocumentVersion = DocumentVersion::create([
                    'entity_id' => $document->entity_id,
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'user_branch_id' => $userBranchId,
                    'pull_request_edit_session_id' => $pullRequestEditSessionId,
                    'status' => DocumentStatus::DRAFT->value,
                    'description' => $document->description,
                    'category_entity_id' => $document->category_entity_id,
                    'title' => $document->title,
                    'deleted_at' => now(),
                    'is_deleted' => Flag::TRUE,
                ]);

                // EditStartVersionを作成
                EditStartVersion::create([
                    'user_branch_id' => $userBranchId,
                    'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                    'original_version_id' => $document->id,
                    'current_version_id' => $newDocumentVersion->id,
                ]);

                // 既存ドキュメントがDRAFTステータスの場合は削除
                if ($document->status === DocumentStatus::DRAFT->value) {
                    $document->delete();
                }

                // プルリクエストを編集している処理を考慮
                if ($pullRequestEditSessionId) {
                    PullRequestEditSessionDiff::updateOrCreate(
                        [
                            'pull_request_edit_session_id' => $pullRequestEditSessionId,
                            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                            'original_version_id' => $document->id,
                        ],
                        [
                            'current_version_id' => $newDocumentVersion->id,
                            'diff_type' => 'deleted',
                        ]
                    );
                }

                $deletedDocumentVersions[] = $newDocumentVersion;
            }

            // 8. categories where parent_entity_id = category_entity_idを元にis_deleted = 1なcategory_versions, edit_start_versionsを生成
            $childCategories = CategoryVersion::where('parent_entity_id', $dto->categoryEntityId)
                ->where('is_deleted', Flag::FALSE)
                ->get();

            foreach ($childCategories as $childCategory) {
                // CategoryVersionを作成（削除用）
                $newCategoryVersion = CategoryVersion::create([
                    'entity_id' => $childCategory->entity_id,
                    'parent_entity_id' => $childCategory->parent_entity_id,
                    'title' => $childCategory->title,
                    'description' => $childCategory->description,
                    'status' => DocumentCategoryStatus::DRAFT->value,
                    'user_branch_id' => $userBranchId,
                    'pull_request_edit_session_id' => $pullRequestEditSessionId,
                    'organization_id' => $organizationId,
                    'deleted_at' => now(),
                    'is_deleted' => Flag::TRUE,
                ]);

                // EditStartVersionを作成
                EditStartVersion::create([
                    'user_branch_id' => $userBranchId,
                    'target_type' => EditStartVersionTargetType::CATEGORY->value,
                    'original_version_id' => $childCategory->id,
                    'current_version_id' => $newCategoryVersion->id,
                ]);

                // 既存カテゴリがDRAFTステータスの場合は削除
                if ($childCategory->status === DocumentCategoryStatus::DRAFT->value) {
                    $childCategory->delete();
                }

                // プルリクエストを編集している処理を考慮
                if ($pullRequestEditSessionId) {
                    PullRequestEditSessionDiff::updateOrCreate(
                        [
                            'pull_request_edit_session_id' => $pullRequestEditSessionId,
                            'target_type' => EditStartVersionTargetType::CATEGORY->value,
                            'original_version_id' => $childCategory->id,
                        ],
                        [
                            'current_version_id' => $newCategoryVersion->id,
                            'diff_type' => 'deleted',
                        ]
                    );
                }

                $deletedCategoryVersions[] = $newCategoryVersion;
            }

            // 9. カテゴリ自体を削除
            $newCategoryVersion = CategoryVersion::create([
                'entity_id' => $existingCategory->entity_id,
                'parent_entity_id' => $existingCategory->parent_entity_id,
                'title' => $existingCategory->title,
                'description' => $existingCategory->description,
                'status' => DocumentCategoryStatus::DRAFT->value,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                'organization_id' => $organizationId,
                'deleted_at' => now(),
                'is_deleted' => Flag::TRUE,
            ]);

            // EditStartVersionを作成
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::CATEGORY->value,
                'original_version_id' => $existingCategory->id,
                'current_version_id' => $newCategoryVersion->id,
            ]);

            // 既存カテゴリがDRAFTステータスの場合は削除
            if ($existingCategory->status === DocumentCategoryStatus::DRAFT->value) {
                $existingCategory->delete();
            }

            // プルリクエストを編集している処理を考慮
            if ($pullRequestEditSessionId) {
                PullRequestEditSessionDiff::updateOrCreate(
                    [
                        'pull_request_edit_session_id' => $pullRequestEditSessionId,
                        'target_type' => EditStartVersionTargetType::CATEGORY->value,
                        'original_version_id' => $existingCategory->id,
                    ],
                    [
                        'current_version_id' => $newCategoryVersion->id,
                        'diff_type' => 'deleted',
                    ]
                );
            }

            $deletedCategoryVersions[] = $newCategoryVersion;

            DB::commit();

            // 10. 削除したdoc & categoryのversionsデータを返却
            return [
                'document_versions' => $deletedDocumentVersions,
                'category_versions' => $deletedCategoryVersions,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw $e;
        }
    }
}


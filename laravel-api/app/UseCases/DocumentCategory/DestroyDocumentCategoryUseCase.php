<?php

namespace App\UseCases\DocumentCategory;

use App\Consts\Flag;
use App\Dto\UseCase\DocumentCategory\DestroyCategoryEntityDto;
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
use App\Services\CategoryService;
use App\Services\DocumentService;
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
        private CategoryService $CategoryService,
        private DocumentService $DocumentService,
    ) {}

    /**
     * カテゴリを削除
     *
     * @param  DestroyCategoryEntityDto  $dto  カテゴリ削除DTO
     * @param  User  $user  認証済みユーザー
     * @return array 削除されたドキュメントとカテゴリのバージョンデータ
     *
     * @throws NotFoundException カテゴリが見つからない場合
     */
    public function execute(DestroyCategoryEntityDto $dto, User $user): array
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

            // 7. category_entity_idで作業コンテキストに応じて、配下のdocument_versionsを再帰的に全て取得
            $documents = $this->DocumentService->getDescendantDocumentsByWorkContext(
                $dto->categoryEntityId,
                $user,
                $dto->pullRequestEditToken
            );

            // 8. category_entity_idで作業コンテキストに応じて、配下のcategory_versionsを再帰的に全て取得
            $categories = $this->CategoryService->getDescendantCategoriesByWorkContext(
                $dto->categoryEntityId,
                $user,
                $dto->pullRequestEditToken
            );

            // 10. documentsとcategoriesのデータを元にis_deleted = 1でbulk insert
            $now = now();
            $documentVersionsData = [];
            $categoryVersionsData = [];
            $editStartVersionsData = [];
            $pullRequestEditSessionDiffsData = [];
            $draftDocumentIds = [];
            $draftCategoryIds = [];

            // ドキュメントバージョンのデータ準備
            foreach ($documents as $document) {
                $documentVersionsData[] = [
                    'entity_id' => $document->entity_id,
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'user_branch_id' => $userBranchId,
                    'pull_request_edit_session_id' => $pullRequestEditSessionId,
                    'status' => DocumentStatus::DRAFT->value,
                    'description' => $document->description,
                    'category_entity_id' => $document->category_entity_id,
                    'title' => $document->title,
                    'deleted_at' => $now,
                    'is_deleted' => Flag::TRUE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // DRAFTステータスのドキュメントIDを収集
                if ($document->status === DocumentStatus::DRAFT->value) {
                    $draftDocumentIds[] = $document->id;
                }
            }

            // カテゴリバージョンのデータ準備
            foreach ($categories as $category) {
                $categoryVersionsData[] = [
                    'entity_id' => $category->entity_id,
                    'parent_entity_id' => $category->parent_entity_id,
                    'title' => $category->title,
                    'description' => $category->description,
                    'status' => DocumentCategoryStatus::DRAFT->value,
                    'user_branch_id' => $userBranchId,
                    'pull_request_edit_session_id' => $pullRequestEditSessionId,
                    'organization_id' => $organizationId,
                    'deleted_at' => $now,
                    'is_deleted' => Flag::TRUE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // DRAFTステータスのカテゴリIDを収集
                if ($category->status === DocumentCategoryStatus::DRAFT->value) {
                    $draftCategoryIds[] = $category->id;
                }
            }

            // 11. DocumentVersionsを一括作成
            if (! empty($documentVersionsData)) {
                DocumentVersion::insert($documentVersionsData);
            }

            // 12. CategoryVersionsを一括作成
            if (! empty($categoryVersionsData)) {
                CategoryVersion::insert($categoryVersionsData);
            }

            // 13. 作成されたバージョンを取得
            $deletedDocumentVersions = collect();
            $deletedCategoryVersions = collect();

            if (! empty($documentVersionsData)) {
                $deletedDocumentVersions = DocumentVersion::withTrashed()
                    ->where('user_branch_id', $userBranchId)
                    ->whereIn('id', array_column($documentVersionsData, 'id'))
                    ->get();

                // EditStartVersionsのデータ準備
                foreach ($documents as $index => $document) {
                    $newVersion = $deletedDocumentVersions->where('id', $document->id)->first();
                    if ($newVersion) {
                        $editStartVersionsData[] = [
                            'user_branch_id' => $userBranchId,
                            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                            'original_version_id' => $document->id,
                            'current_version_id' => $newVersion->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        // PullRequestEditSessionDiffのデータ準備
                        if ($pullRequestEditSessionId) {
                            $pullRequestEditSessionDiffsData[] = [
                                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                                'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                                'original_version_id' => $document->id,
                                'current_version_id' => $newVersion->id,
                                'diff_type' => 'deleted',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }
            }

            if (! empty($categoryVersionsData)) {
                $deletedCategoryVersions = CategoryVersion::withTrashed()
                    ->where('user_branch_id', $userBranchId)
                    ->whereIn('id', array_column($categoryVersionsData, 'id'))
                    ->get();

                // EditStartVersionsのデータ準備
                foreach ($categories as $index => $category) {
                    $newVersion = $deletedCategoryVersions->where('id', $category->id)->first();
                    if ($newVersion) {
                        $editStartVersionsData[] = [
                            'user_branch_id' => $userBranchId,
                            'target_type' => EditStartVersionTargetType::CATEGORY->value,
                            'original_version_id' => $category->id,
                            'current_version_id' => $newVersion->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        // PullRequestEditSessionDiffのデータ準備
                        if ($pullRequestEditSessionId) {
                            $pullRequestEditSessionDiffsData[] = [
                                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                                'target_type' => EditStartVersionTargetType::CATEGORY->value,
                                'original_version_id' => $category->id,
                                'current_version_id' => $newVersion->id,
                                'diff_type' => 'deleted',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }
            }

            // 14. EditStartVersionsを一括作成
            if (! empty($editStartVersionsData)) {
                EditStartVersion::insert($editStartVersionsData);
            }

            // 15. PullRequestEditSessionDiffsを一括作成（upsert使用）
            if (! empty($pullRequestEditSessionDiffsData)) {
                PullRequestEditSessionDiff::upsert(
                    $pullRequestEditSessionDiffsData,
                    ['pull_request_edit_session_id', 'target_type', 'original_version_id'],
                    ['current_version_id', 'diff_type', 'updated_at']
                );
            }

            // 16. DRAFTステータスのレコードを一括削除
            if (! empty($draftDocumentIds)) {
                DocumentVersion::whereIn('id', $draftDocumentIds)->delete();
            }

            if (! empty($draftCategoryIds)) {
                CategoryVersion::whereIn('id', $draftCategoryIds)->delete();
            }

            DB::commit();

            // 17. 削除したdoc & categoryのversionsデータを全て返却
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

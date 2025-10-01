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

            $deletedDocumentVersions = [];
            $deletedCategoryVersions = [];

            // 9. $documentsを削除したversionsレコードとedit_start_versionsを一括作成
            foreach ($documents as $document) {
                $deletedVersion = $this->createDeletedDocumentVersion(
                    $document,
                    $user,
                    $organizationId,
                    $userBranchId,
                    $pullRequestEditSessionId
                );

                $deletedDocumentVersions[] = $deletedVersion;
            }

            // 10. $categoriesを削除したversionsレコードとedit_start_versionsを一括作成
            foreach ($categories as $category) {
                $deletedVersion = $this->createDeletedCategoryVersion(
                    $category,
                    $user,
                    $organizationId,
                    $userBranchId,
                    $pullRequestEditSessionId
                );

                $deletedCategoryVersions[] = $deletedVersion;
            }

            // 11. カテゴリ自体を削除
            $deletedCategoryVersion = $this->createDeletedCategoryVersion(
                $categoryEntity,
                $user,
                $organizationId,
                $userBranchId,
                $pullRequestEditSessionId
            );

            $deletedCategoryVersions[] = $deletedCategoryVersion;

            DB::commit();

            // 12. 削除したdoc & categoryのversionsデータを全て返却
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

    /**
     * 削除用のドキュメントバージョンを作成
     */
    private function createDeletedDocumentVersion(
        DocumentVersion $document,
        User $user,
        int $organizationId,
        int $userBranchId,
        ?int $pullRequestEditSessionId
    ): DocumentVersion {
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

        return $newDocumentVersion;
    }

    /**
     * 削除用のカテゴリバージョンを作成
     */
    private function createDeletedCategoryVersion(
        CategoryVersion $category,
        User $user,
        int $organizationId,
        int $userBranchId,
        ?int $pullRequestEditSessionId
    ): CategoryVersion {
        // CategoryVersionを作成（削除用）
        $newCategoryVersion = CategoryVersion::create([
            'entity_id' => $category->entity_id,
            'parent_entity_id' => $category->parent_entity_id,
            'title' => $category->title,
            'description' => $category->description,
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
            'original_version_id' => $category->id,
            'current_version_id' => $newCategoryVersion->id,
        ]);

        // 既存カテゴリがDRAFTステータスの場合は削除
        if ($category->status === DocumentCategoryStatus::DRAFT->value) {
            $category->delete();
        }

        // プルリクエストを編集している処理を考慮
        if ($pullRequestEditSessionId) {
            PullRequestEditSessionDiff::updateOrCreate(
                [
                    'pull_request_edit_session_id' => $pullRequestEditSessionId,
                    'target_type' => EditStartVersionTargetType::CATEGORY->value,
                    'original_version_id' => $category->id,
                ],
                [
                    'current_version_id' => $newCategoryVersion->id,
                    'diff_type' => 'deleted',
                ]
            );
        }

        return $newCategoryVersion;
    }
}

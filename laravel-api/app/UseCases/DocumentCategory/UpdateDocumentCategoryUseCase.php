<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\UpdateDocumentCategoryDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryVersion;
use App\Models\CategoryEntity;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Services\CategoryService;
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
        private CategoryService $CategoryService,
    ) {}

    /**
     * ドキュメントカテゴリを更新
     *
     * @param  UpdateDocumentCategoryDto  $dto  カテゴリ更新DTO
     * @param  User  $user  認証済みユーザー
     * @return CategoryVersion 更新されたカテゴリ
     *
     * @throws NotFoundException 組織またはカテゴリが見つからない場合
     */
    public function execute(UpdateDocumentCategoryDto $dto, User $user): CategoryVersion
    {
        try {
            DB::beginTransaction();

            // 1. $organizationId = $user->organizationMember->organization_id;
            $organizationId = $user->organizationMember->organization_id;

            // 2. if $organizationない場合 throw new NotFoundException;
            if (! $organizationId) {
                throw new NotFoundException;
            }

            $categoryEntity = CategoryEntity::find($dto->categoryEntityId);

            if (! $categoryEntity) {
                throw new NotFoundException;
            }

            // 3. fetchOrCreateActiveBranch
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $organizationId,
            );

            // 5. 編集対象のexistingCategoryを取得
            $existingCategory = $this->CategoryService->getCategoryByWorkContext(
                $dto->categoryEntityId,
                $user,
            );

            // 6. if existingCategoryがない場合 throw new NotFoundException;
            if (! $existingCategory) {
                throw new NotFoundException;
            }

            // 7. CategoryVersionを作成
            $newCategory = CategoryVersion::create([
                'entity_id' => $categoryEntity->id,
                'title' => $dto->title,
                'parent_entity_id' => $existingCategory->parent_entity_id,
                'description' => $dto->description,
                'user_branch_id' => $userBranchId,
                'organization_id' => $organizationId,
                'status' => DocumentCategoryStatus::DRAFT->value,
            ]);

            // 8. EditStartVersionを作成(original_version_id = existingCategory.id, current_version_id = 新規のDocumentCategory.id)
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::CATEGORY->value,
                'original_version_id' => $existingCategory->id,
                'current_version_id' => $newCategory->id,
            ]);

            if ($existingCategory->status === DocumentCategoryStatus::DRAFT->value) {
                $existingCategory->delete();
            }

            DB::commit();

            return $newCategory;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw $e;
        }
    }
}

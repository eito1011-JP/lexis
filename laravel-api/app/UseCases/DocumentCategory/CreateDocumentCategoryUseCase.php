<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\CreateDocumentCategoryDto;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryVersion;
use App\Models\CategoryEntity;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateDocumentCategoryUseCase
{
    public function __construct(
        private UserBranchService $userBranchService,
    ) {}

    /**
     * ドキュメントカテゴリを作成
     *
     * @param  CreateDocumentCategoryDto  $dto  カテゴリ作成DTO
     * @param  User  $user  認証済みユーザー
     * @return CategoryVersion 作成されたカテゴリ
     */
    public function execute(CreateDocumentCategoryDto $dto, User $user): CategoryVersion
    {
        try {
            DB::beginTransaction();
            $organizationId = $user->organizationMember->organization_id;

            $organization = Organization::find($organizationId);
            if (! $organization) {
                throw new NotFoundException;
            }

            // ユーザーブランチIDを取得または作成
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $organizationId,
            );

            // カテゴリエンティティを作成
            $categoryEntity = CategoryEntity::create([
                'organization_id' => $organizationId,
            ]);

            // カテゴリを作成
            $category = CategoryVersion::create([
                'entity_id' => $categoryEntity->id,
                'title' => $dto->title,
                'description' => $dto->description,
                'user_branch_id' => $userBranchId,
                'parent_entity_id' => $dto->parentEntityId,
                'organization_id' => $organizationId,
            ]);

            // EditStartVersionを作成
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::CATEGORY->value,
                'original_version_id' => $category->id,
                'current_version_id' => $category->id,
            ]);

            DB::commit();

            return $category;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e);

            throw $e;
        }
    }
}

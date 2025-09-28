<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\GetCategoryDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\DocumentCategoryEntity;
use App\Models\EditStartVersion;
use App\Models\UserBranch;
use Http\Discovery\Exception\NotFoundException;

/**
 * カテゴリ詳細取得UseCase
 */
class GetCategoryUseCase
{
    /**
     * カテゴリ詳細を取得
     *
     * @throws NotFoundException
     */
    public function execute(GetCategoryDto $dto): array
    {
        // ユーザーのアクティブブランチを取得
        $activeUserBranch = UserBranch::where('user_id', $dto->user->id)->active()->first();
        $categoryEntity = DocumentCategoryEntity::find($dto->categoryEntityId);

        if (!$categoryEntity) {
            throw new NotFoundException('カテゴリエンティティが見つかりません。');
        }

        // 作業コンテキストに応じて適切なカテゴリを取得
        $category = $this->getCategoryByWorkContext($dto, $activeUserBranch);

        if (!$category) {
            throw new NotFoundException('カテゴリが見つかりません。');
        }

        // パンクズリストを生成
        $breadcrumbs = $category->getBreadcrumbs();

        return [
            'id' => $category->id,
            'entity_id' => $category->entity_id,
            'title' => $category->title,
            'description' => $category->description,
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    /**
     * 作業コンテキストに応じて適切なカテゴリを取得
     */
    private function getCategoryByWorkContext(GetCategoryDto $dto, ?UserBranch $activeUserBranch): ?DocumentCategory
    {
        $baseQuery = DocumentCategory::with(['parent.parent.parent.parent.parent.parent.parent']) // 7階層まで親カテゴリを読み込み
            ->where('entity_id', $dto->categoryEntityId)
            ->where('organization_id', $dto->user->organizationMember->organization_id);

        if (!$activeUserBranch) {
            // アクティブなユーザーブランチがない場合：MERGEDステータスのみ取得
            return $baseQuery->where('status', DocumentCategoryStatus::MERGED->value)->first();
        }

        // EditStartVersionから現在のバージョンIDを取得
        $currentVersionId = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
            ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
            ->where('original_version_id', function ($query) use ($dto) {
                $query->select('id')
                    ->from('document_categories')
                    ->where('entity_id', $dto->categoryEntityId)
                    ->where('status', DocumentCategoryStatus::MERGED->value);
            })
            ->value('current_version_id');

        if ($currentVersionId) {
            // EditStartVersionに登録されている場合は、現在のバージョンを取得
            $category = $baseQuery->where('id', $currentVersionId)->first();
            if ($category) {
                return $category;
            }
        }

        if ($dto->pullRequestEditSessionToken) {
            // 再編集している場合：PUSHEDとDRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->whereIn('status', [
                        DocumentCategoryStatus::PUSHED->value,
                        DocumentCategoryStatus::DRAFT->value,
                    ])
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentCategoryStatus::MERGED->value);
            })->orderBy('created_at', 'desc')->first();
        } else {
            // 初回編集の場合：DRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->where('status', DocumentCategoryStatus::DRAFT->value)
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentCategoryStatus::MERGED->value);
            })->orderBy('created_at', 'desc')->first();
        }
    }
}

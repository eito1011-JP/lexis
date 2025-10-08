<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryVersion;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Collection;

class FetchCategoriesUseCase
{
    /**
     * 認証ユーザーのカテゴリ一覧を取得
     *
     * @param  FetchCategoriesDto  $dto  リクエストDTO
     * @param  User  $user  認証ユーザー
     */
    public function execute(FetchCategoriesDto $dto, User $user): Collection
    {
        // ユーザーのアクティブブランチを取得
        $activeUserBranch = UserBranch::where('user_id', $user->id)->active()->first();

        if (! $activeUserBranch) {
            // アクティブなユーザーブランチがない場合：MERGEDステータスのみ取得
            return CategoryVersion::select('id', 'entity_id', 'title')
                ->where('parent_entity_id', $dto->parentEntityId)
                ->where('organization_id', $user->organizationMember->organization_id)
                ->where('status', DocumentCategoryStatus::MERGED->value)
                ->orderBy('created_at', 'asc')
                ->get();
        }

        // EditStartVersionから現在のバージョンIDを取得
        $currentVersionIds = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
            ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
            ->pluck('current_version_id');

            // 初回編集の場合：DRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
            // ただし、編集対象となったカテゴリは除外する
            $editedCategoryIds = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
                ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
                ->whereColumn('original_version_id', '!=', 'current_version_id')
                ->pluck('original_version_id');

            return CategoryVersion::select('id', 'entity_id', 'title')
                ->where('parent_entity_id', $dto->parentEntityId)
                ->where('organization_id', $user->organizationMember->organization_id)
                ->whereIn('id', $currentVersionIds)
                ->whereNotIn('id', $editedCategoryIds) // 編集対象となった元のカテゴリを除外
                ->where(function ($query) use ($activeUserBranch) {
                    $query->where(function ($q1) use ($activeUserBranch) {
                        $q1->where('status', DocumentCategoryStatus::DRAFT->value)
                            ->where('user_branch_id', $activeUserBranch->id);
                    })->orWhere('status', DocumentCategoryStatus::MERGED->value);
                })
                ->orderBy('created_at', 'asc')
                ->get();
    }
}

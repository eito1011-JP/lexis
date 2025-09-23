<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
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

        $query = DocumentCategory::select('id', 'title')
            ->where('parent_id', $dto->parentId)
            ->where('organization_id', $user->organizationMember->organization_id);

        if ($activeUserBranch) {
            // activeなuser_branchがある場合
            if ($dto->pullRequestEditSessionToken) {
                // 再編集している場合：PUSHEDとDRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
                $query->where(function ($subQuery) use ($activeUserBranch) {
                    $subQuery->where(function ($q1) use ($activeUserBranch) {
                        $q1->whereIn('status', [
                            DocumentCategoryStatus::PUSHED->value,
                            DocumentCategoryStatus::DRAFT->value,
                        ])
                            ->where('user_branch_id', $activeUserBranch->id);
                    })->orWhere('status', DocumentCategoryStatus::MERGED->value);
                });
            } else {
                // 初回編集の場合：DRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
                // ただし、編集対象となったカテゴリは除外する（新規作成は除外しない）
                $editedCategoryIds = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
                    ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
                    ->whereColumn('original_version_id', '!=', 'current_version_id') // 新規作成は除外（original_version_id = current_version_id）
                    ->pluck('original_version_id');

                $query->where(function ($subQuery) use ($activeUserBranch, $editedCategoryIds) {
                    $subQuery->where(function ($q1) use ($activeUserBranch, $editedCategoryIds) {
                        $q1->where('status', DocumentCategoryStatus::DRAFT->value)
                            ->where('user_branch_id', $activeUserBranch->id)
                            ->whereNotIn('id', $editedCategoryIds);
                    })->orWhere(function ($q2) use ($editedCategoryIds) {
                        $q2->where('status', DocumentCategoryStatus::MERGED->value)
                            ->whereNotIn('id', $editedCategoryIds);
                    });
                });
            }
        } else {
            // 未編集の場合：MERGEDステータスのみ取得
            $query->where('status', DocumentCategoryStatus::MERGED->value);
        }

        // ステータス優先度とcreated_atで並び替え
        // PUSHEDが最初、DRAFTが後、同一ステータス内ではcreated_at昇順
        return $query->orderBy('created_at', 'asc')->get();
    }
}

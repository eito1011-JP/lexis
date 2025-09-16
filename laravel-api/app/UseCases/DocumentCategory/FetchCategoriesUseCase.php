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
                // 再編集している場合：PUSHEDとDRAFTステータスの両方を取得（自分のユーザーブランチのもの）
                $query->whereIn('status', [
                    DocumentCategoryStatus::PUSHED->value,
                    DocumentCategoryStatus::DRAFT->value
                ])
                ->where('user_branch_id', $activeUserBranch->id);
            } else {
                // 初回編集の場合：DRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
                // ただし、編集対象となったMERGEDカテゴリは除外する（新規作成は除外しない）
                $editedMergedCategoryIds = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
                    ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
                    ->whereColumn('original_version_id', '!=', 'current_version_id') // 新規作成は除外（original_version_id = current_version_id）
                    ->whereHas('originalCategory', function($q) {
                        $q->where('status', DocumentCategoryStatus::MERGED->value);
                    })
                    ->pluck('original_version_id');

                $query->where(function($subQuery) use ($activeUserBranch, $editedMergedCategoryIds) {
                    $subQuery->where(function($q1) use ($activeUserBranch) {
                        $q1->where('status', DocumentCategoryStatus::DRAFT->value)
                           ->where('user_branch_id', $activeUserBranch->id);
                    })->orWhere(function($q2) use ($editedMergedCategoryIds) {
                        $q2->where('status', DocumentCategoryStatus::MERGED->value)
                           ->whereNotIn('id', $editedMergedCategoryIds);
                    });
                });
            }
        } else {
            // 未編集の場合：MERGEDステータスのみ取得
            $query->where('status', DocumentCategoryStatus::MERGED->value);
        }

        // ステータス優先度とcreated_atで並び替え
        // PUSHEDが最初、DRAFTが後、同一ステータス内ではcreated_at昇順
        return $query->orderByRaw("CASE status WHEN 'pushed' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'asc')
            ->get();
    }
}

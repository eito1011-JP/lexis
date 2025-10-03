<?php

namespace App\Services;

use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\CategoryVersion;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{

    /**
     * 作業コンテキストに応じて適切なカテゴリを取得
     */
    public function getCategoryByWorkContext(
        int $categoryEntityId,
        User $user,
        ?string $pullRequestEditSessionToken = null
    ): ?CategoryVersion {
        // ユーザーのアクティブブランチを取得
        $activeUserBranch = UserBranch::where('user_id', $user->id)->active()->first();

        $baseQuery = CategoryVersion::with(['parent.parent.parent.parent.parent.parent.parent']) // 7階層まで親カテゴリを読み込み
            ->where('entity_id', $categoryEntityId)
            ->where('organization_id', $user->organizationMember->organization_id);

        if (! $activeUserBranch) {
            // アクティブなユーザーブランチがない場合：MERGEDステータスのみ取得
            return $baseQuery->where('status', DocumentCategoryStatus::MERGED->value)->first();
        }

        // EditStartVersionから現在のバージョンIDを取得
        // entity_idに基づいて検索し、最新のEditStartVersionレコードを取得
        $editStartVersion = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
            ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
            ->whereIn('original_version_id', function ($query) use ($categoryEntityId) {
                $query->select('id')
                    ->from('category_versions')
                    ->where('entity_id', $categoryEntityId)
                    ->where('status', DocumentCategoryStatus::MERGED->value);
            })
            ->orderBy('id', 'desc')
            ->first();

        if ($editStartVersion) {
            // EditStartVersionに登録されている場合は、現在のバージョンを取得
            $category = $baseQuery->where('id', $editStartVersion->current_version_id)->first();
            if ($category) {
                return $category;
            }
        }

        if ($pullRequestEditSessionToken) {
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

    /**
     * 作業コンテキストに応じて配下の全カテゴリを再帰的に取得
     *
     * @param  int  $categoryEntityId  対象カテゴリのエンティティID
     * @param  User  $user  認証済みユーザー
     * @param  ?string  $pullRequestEditSessionToken  プルリクエスト編集トークン
     * @return Collection カテゴリバージョンのコレクション
     */
    public function getDescendantCategoriesByWorkContext(
        int $categoryEntityId,
        User $user,
        ?string $pullRequestEditSessionToken = null
    ): Collection {
        $activeUserBranch = UserBranch::where('user_id', $user->id)->active()->first();
        $organizationId = $user->organizationMember->organization_id;
        $descendants = new Collection();

        // 直下の子カテゴリを取得
        $childCategories = $this->getChildCategoriesByWorkContext(
            $categoryEntityId,
            $organizationId,
            $activeUserBranch,
            $pullRequestEditSessionToken
        );

        foreach ($childCategories as $childCategory) {
            $descendants->push($childCategory);

            // 再帰的に孫カテゴリを取得
            $grandChildren = $this->getDescendantCategoriesByWorkContext(
                $childCategory->entity_id,
                $user,
                $pullRequestEditSessionToken
            );

            $descendants = $descendants->merge($grandChildren);
        }

        return $descendants;
    }

    /**
     * 作業コンテキストに応じて直下の子カテゴリを取得
     */
    public function getChildCategoriesByWorkContext(
        int $parentEntityId,
        int $organizationId,
        ?UserBranch $activeUserBranch,
        ?string $pullRequestEditSessionToken
    ): Collection {
        $baseQuery = CategoryVersion::where('parent_entity_id', $parentEntityId)
            ->where('organization_id', $organizationId);

        if (! $activeUserBranch) {
            return $baseQuery->where('status', DocumentCategoryStatus::MERGED->value)->get();
        }

        if ($pullRequestEditSessionToken) {
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->whereIn('status', [
                        DocumentCategoryStatus::PUSHED->value,
                        DocumentCategoryStatus::DRAFT->value,
                    ])
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentCategoryStatus::MERGED->value);
            })->get();
        } else {
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->where('status', DocumentCategoryStatus::DRAFT->value)
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentCategoryStatus::MERGED->value);
            })->get();
        }
    }
}

<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\FixRequestStatus;
use App\Models\CategoryVersion;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\FixRequest;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Collection;

class DocumentCategoryService
{
    /**
     * positionの重複処理・自動採番を行う
     *
     * @param  int|null  $requestedPosition  リクエストされたposition
     * @param  int|null  $parentId  親カテゴリID
     * @return int 正規化されたposition
     */
    public function normalizePosition(?int $requestedPosition, ?int $parentId = null): int
    {
        if ($requestedPosition) {
            // position重複時、既存のposition >= 入力値を+1してずらす
            CategoryVersion::where('parent_entity_id', $parentId)
                ->where('position', '>=', $requestedPosition)
                ->increment('position');

            return $requestedPosition;
        } else {
            // position未入力時、親カテゴリ内最大値+1をセット
            $maxPosition = CategoryVersion::where('parent_entity_id', $parentId)
                ->max('position') ?? 0;

            return $maxPosition + 1;
        }
    }

    /**
     * 親カテゴリのパスを取得
     */
    public function createCategoryPath(CategoryVersion $categoryVersion): ?string
    {
        if (! $categoryVersion->parent_entity_id) {
            return null;
        }

        $path = [];
        $parentCategory = CategoryVersion::find($categoryVersion->parent_entity_id);

        while ($parentCategory) {
            array_unshift($path, $parentCategory->title);
            $parentCategory = $parentCategory->parent_entity_id ? CategoryVersion::find($parentCategory->parent_entity_id) : null;
        }

        return implode('/', $path);
    }

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
        $currentVersionId = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
            ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
            ->where('original_version_id', function ($query) use ($categoryEntityId) {
                $query->select('id')
                    ->from('category_versions')
                    ->where('entity_id', $categoryEntityId)
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
     * 作業コンテキストに応じて配下の全ドキュメントを再帰的に取得
     *
     * @param  int  $categoryEntityId  対象カテゴリのエンティティID
     * @param  User  $user  認証済みユーザー
     * @param  ?string  $pullRequestEditSessionToken  プルリクエスト編集トークン
     * @return Collection ドキュメントバージョンのコレクション
     */
    public function getDescendantDocumentsByWorkContext(
        int $categoryEntityId,
        User $user,
        ?string $pullRequestEditSessionToken = null
    ): Collection {
        $activeUserBranch = UserBranch::where('user_id', $user->id)->active()->first();
        $organizationId = $user->organizationMember->organization_id;
        $documents = new Collection();

        // 直下のドキュメントを取得
        $directDocuments = $this->getDocumentsByWorkContext(
            $categoryEntityId,
            $organizationId,
            $activeUserBranch,
            $pullRequestEditSessionToken
        );

        $documents = $documents->merge($directDocuments);

        // 子カテゴリ配下のドキュメントを再帰的に取得
        $childCategories = $this->getChildCategoriesByWorkContext(
            $categoryEntityId,
            $organizationId,
            $activeUserBranch,
            $pullRequestEditSessionToken
        );

        foreach ($childCategories as $childCategory) {
            $childDocuments = $this->getDescendantDocumentsByWorkContext(
                $childCategory->entity_id,
                $user,
                $pullRequestEditSessionToken
            );

            $documents = $documents->merge($childDocuments);
        }

        return $documents;
    }

    /**
     * 作業コンテキストに応じて直下の子カテゴリを取得
     */
    private function getChildCategoriesByWorkContext(
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

    /**
     * 作業コンテキストに応じて直下のドキュメントを取得
     */
    private function getDocumentsByWorkContext(
        int $categoryEntityId,
        int $organizationId,
        ?UserBranch $activeUserBranch,
        ?string $pullRequestEditSessionToken
    ): Collection {
        $baseQuery = DocumentVersion::where('category_entity_id', $categoryEntityId)
            ->where('organization_id', $organizationId);

        if (! $activeUserBranch) {
            return $baseQuery->where('status', DocumentStatus::MERGED->value)->get();
        }

        if ($pullRequestEditSessionToken) {
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->whereIn('status', [
                        DocumentStatus::PUSHED->value,
                        DocumentStatus::DRAFT->value,
                    ])
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentStatus::MERGED->value);
            })->get();
        } else {
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->where('status', DocumentStatus::DRAFT->value)
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentStatus::MERGED->value);
            })->get();
        }
    }
}

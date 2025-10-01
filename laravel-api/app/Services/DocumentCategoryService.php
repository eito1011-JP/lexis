<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\FixRequestStatus;
use App\Models\CategoryVersion;
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

    // /**
    //  * カテゴリパスからparentとなるcategory idを再帰的に取得
    //  *
    //  * @param  string|null  $categoryPath
    //  *                                     parent/child/grandchildのカテゴリパスの場合、'parent/child/grandchild'の文字列を期待
    //  * @return int カテゴリID
    //  *
    //  * @throws InvalidArgumentException 不正なパス形式または存在しないカテゴリの場合
    //  */
    // public function getIdFromPath(?string $categoryPath): int
    // {
    //     if (empty($categoryPath)) {
    //         return DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
    //     }

    //     // 正しいパス形式（英数字、ハイフン、アンダースコアのセグメントをスラッシュで区切った形式）以外は無効
    //     if (! preg_match('/^[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*$/', $categoryPath)) {
    //         throw new InvalidArgumentException('Invalid path format: path must contain only alphanumeric characters, hyphens, and underscores separated by single slashes');
    //     }

    //     // スラッシュでパスを分割
    //     $pathSegments = explode('/', $categoryPath);

    //     // デフォルトカテゴリ（uncategorized）から開始
    //     $currentParentCategoryId = DocumentCategoryConstants::DEFAULT_CATEGORY_ID;

    //     foreach ($pathSegments as $slug) {
    //         $category = DocumentCategory::where('slug', $slug)
    //             ->where('parent_entity_id', $currentParentCategoryId)
    //             ->first();

    //         if (! $category) {
    //             throw new InvalidArgumentException("Category not found: {$slug}");
    //         }

    //         $currentParentCategoryId = $category->id;
    //     }

    //     return $currentParentCategoryId;
    // }

    /**
     * サブカテゴリを取得（ブランチ別）
     */
    public function getSubCategories(
        int $parentId,
        ?int $userBranchId = null,
        ?int $editPullRequestId = null
    ): Collection {
        $query = CategoryVersion::select('sidebar_label', 'position')
            ->where('parent_entity_id', $parentId)
            ->where(function ($q) use ($userBranchId) {
                $q->where('status', 'merged')
                    ->orWhere(function ($subQ) use ($userBranchId) {
                        $subQ->where('user_branch_id', $userBranchId)
                            ->where('status', DocumentCategoryStatus::DRAFT->value);
                    });
            })
            ->when($editPullRequestId, function ($query, $editPullRequestId) {
                return $query->orWhere(function ($subQ) use ($editPullRequestId) {
                    $subQ->whereHas('userBranch.pullRequests', function ($prQ) use ($editPullRequestId) {
                        $prQ->where('id', $editPullRequestId);
                    })
                        ->where('status', DocumentCategoryStatus::PUSHED->value);
                });
            })
            ->when($userBranchId, function ($query, $userBranchId) {
                $appliedFixRequestCategoryIds = FixRequest::where('status', FixRequestStatus::APPLIED->value)
                    ->whereNotNull('document_category_id')
                    ->whereHas('categoryVersion', function ($q) use ($userBranchId) {
                        $q->where('user_branch_id', $userBranchId);
                    })
                    ->pluck('document_category_id')
                    ->toArray();

                if (! empty($appliedFixRequestCategoryIds)) {
                    $query->orWhereIn('id', $appliedFixRequestCategoryIds);
                }
            });

        return $query->orderBy('position', 'asc')->get();
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
}

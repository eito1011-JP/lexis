<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Enums\DocumentCategoryStatus;
use App\Enums\FixRequestStatus;
use App\Models\DocumentCategory;
use App\Models\FixRequest;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

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
            DocumentCategory::where('parent_entity_id', $parentId)
                ->where('position', '>=', $requestedPosition)
                ->increment('position');

            return $requestedPosition;
        } else {
            // position未入力時、親カテゴリ内最大値+1をセット
            $maxPosition = DocumentCategory::where('parent_entity_id', $parentId)
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
        $query = DocumentCategory::select('sidebar_label', 'position')
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
                    ->whereHas('documentCategory', function ($q) use ($userBranchId) {
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
    public function createCategoryPath(DocumentCategory $documentCategory): ?string
    {
        if (! $documentCategory->parent_entity_id) {
            return null;
        }

        $path = [];
        $parentCategory = DocumentCategory::find($documentCategory->parent_entity_id);

        while ($parentCategory) {
            array_unshift($path, $parentCategory->title);
            $parentCategory = $parentCategory->parent_entity_id ? DocumentCategory::find($parentCategory->parent_entity_id) : null;
        }

        return implode('/', $path);
    }
}

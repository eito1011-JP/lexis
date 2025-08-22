<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Enums\DocumentCategoryStatus;
use App\Enums\FixRequestStatus;
use App\Models\DocumentCategory;
use App\Models\FixRequest;
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
            DocumentCategory::where('parent_id', $parentId)
                ->where('position', '>=', $requestedPosition)
                ->increment('position');

            return $requestedPosition;
        } else {
            // position未入力時、親カテゴリ内最大値+1をセット
            $maxPosition = DocumentCategory::where('parent_id', $parentId)
                ->max('position') ?? 0;

            return $maxPosition + 1;
        }
    }

    /**
     * カテゴリパスからparentとなるcategory idを再帰的に取得
     *
     * @param  string  $categoryPath
     *                                parent/child/grandchildのカテゴリパスの場合, リクエストとして期待するのは'parent/child/grandchild'のような文字列
     * @return int|null カテゴリID
     */
    public function getIdFromPath(string $categoryPath): ?int
    {
        if (empty($categoryPath)) {
            return DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
        }

        // スラッシュでパスを分割
        $pathSegments = explode('/', $categoryPath);

        // 空のセグメントを除去
        $pathSegments = array_filter($pathSegments, function ($segment) {
            return ! empty($segment);
        });

        if (empty($pathSegments)) {
            return DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
        }

        // デフォルトカテゴリ（uncategorized）から開始
        $parentId = DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
        $currentParentCategoryId = null;

        foreach ($pathSegments as $slug) {
            $category = DocumentCategory::where('slug', $slug)
                ->where('parent_id', $parentId)
                ->first();

            if (! $category) {
                return DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
            }

            $currentParentCategoryId = $category->id;
            $parentId = $category->id;
        }

        return $currentParentCategoryId;
    }

    /**
     * サブカテゴリを取得（ブランチ別）
     */
    public function getSubCategories(
        int $parentId,
        ?int $userBranchId = null,
        ?int $editPullRequestId = null
    ): Collection {
        $query = DocumentCategory::select('slug', 'sidebar_label', 'position')
            ->where('parent_id', $parentId)
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
}

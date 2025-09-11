<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Models\DocumentCategory;
use App\Models\UserBranch;
use App\Enums\DocumentCategoryStatus;
use Illuminate\Database\Eloquent\Collection;

class FetchCategoriesUseCase
{
    /**
     * 認証ユーザーのカテゴリ一覧を取得
     *
     * @param FetchCategoriesDto $dto リクエストDTO
     * @param int $userId 認証ユーザーID
     * @return Collection
     */
    public function execute(FetchCategoriesDto $dto, int $userId): Collection
    {
        // ユーザーのアクティブブランチを取得
        $activeUserBranch = UserBranch::where('user_id', $userId)->active()->first();

        $query = DocumentCategory::select('id', 'title');

        if ($activeUserBranch) {
            // activeなuser_branchがある場合
            if ($activeUserBranch->id === null || $dto->pullRequestEditSessionToken === null) {
                // 再編集している場合
                $query->where('status', DocumentCategoryStatus::MERGED->value)
                      ->where('parent_id', $dto->parentId);
            } else {
                $query->where('status', DocumentCategoryStatus::DRAFT->value)
                      ->where('parent_id', $dto->parentId)
                      ->where('user_id', $userId);
            }
        } else {
            // 未編集の場合
            $query->where('status', DocumentCategoryStatus::MERGED->value)
                  ->where('parent_id', $dto->parentId);
        }

        return $query->orderBy('position', 'asc')->get();
    }
}

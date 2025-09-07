<?php

namespace App\UseCases\DocumentCategory;

use App\Models\DocumentCategory;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Collection;

class FetchCategoriesUseCase
{
    /**
     * 認証ユーザーのカテゴリ一覧を取得
     *
     * @param int $userId 認証ユーザーID
     * @param int|null $parentId 親カテゴリID（nullの場合はルートカテゴリを取得）
     * @return Collection
     */
    public function execute(int $userId, ?int $parentId = null): Collection
    {
        // ユーザーのアクティブブランチを取得
        $userBranch = UserBranch::getActiveBranch($userId);

        $query = DocumentCategory::select('id', 'title');

        if ($userBranch) {
            // アクティブブランチがある場合
            $query->where('user_branch_id', $userBranch->id)
                  ->where('parent_id', $parentId);
        } else {
            // アクティブブランチがない場合、parent_idがnullのカテゴリを取得
            $query->whereNull('parent_id');
        }

        return $query->orderBy('position', 'asc')->get();
    }
}

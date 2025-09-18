<?php

namespace App\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequestEditSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FetchNodesUseCase
{
    /**
     * 指定されたカテゴリに従属するカテゴリとドキュメントを取得
     *
     * @param  FetchNodesDto  $dto  リクエストデータのDTO
     * @param  User  $user  認証済みユーザー
     */
    public function execute(FetchNodesDto $dto, User $user): array
    {
        try {
            $categoryId = $dto->categoryId;
            $pullRequestEditSessionToken = $dto->pullRequestEditSessionToken;

            // 認証ユーザーに紐づくactiveなuser_branchを確認
            $activeUserBranch = $user->userBranches()->active()->first();

            // user_branch_idを設定
            $userBranchId = $activeUserBranch->id;

            // pull_request_edit_session_tokenが提供されている場合の処理
            $pullRequestEditSessionId = null;
            if ($pullRequestEditSessionToken) {
                $editSession = PullRequestEditSession::where('token', $pullRequestEditSessionToken)->first();
                if ($editSession) {
                    $pullRequestEditSessionId = $editSession->id;
                    $userBranchId = $editSession->user_branch_id;
                }
            }

            // カテゴリとドキュメントの取得条件を決定
            if ($userBranchId === null || $pullRequestEditSessionToken === null) {
                // status = merged and parent_id = request.parent_id
                $categories = $this->fetchMergedCategories($categoryId);
                $documents = $this->fetchMergedDocuments($categoryId);
            } else {
                // status = draft AND parent_id = request.parent_id AND user_id = login.user_id
                $categories = $this->fetchDraftCategories($categoryId, $user->id, $userBranchId);
                $documents = $this->fetchDraftDocuments($categoryId, $user->id, $userBranchId);
            }

            // レスポンス形式に変換
            $formattedCategories = $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'sidebar_label' => $category->title,
                ];
            });

            $formattedDocuments = $documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'sidebar_label' => $document->title,
                    'status' => $document->status,
                    'last_edited_by' => $document->last_edited_by,
                ];
            });

            return [
                'documents' => $formattedDocuments->values(),
                'categories' => $formattedCategories->values(),
            ];

        } catch (\Exception $e) {
            Log::error($e);

            throw $e;
        }
    }

    /**
     * マージ済みカテゴリを取得
     */
    private function fetchMergedCategories(int $parentId)
    {
        return DocumentCategory::where('parent_id', $parentId)
            ->where('status', 'merged')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * マージ済みドキュメントを取得
     */
    private function fetchMergedDocuments(int $categoryId)
    {
        return DocumentVersion::where('category_id', $categoryId)
            ->where('status', 'merged')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * ドラフト状態のカテゴリを取得
     */
    private function fetchDraftCategories(int $parentId, int $userId, int $userBranchId)
    {
        return DocumentCategory::where('parent_id', $parentId)
            ->where('status', 'draft')
            ->where('user_branch_id', $userBranchId)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * ドラフト状態のドキュメントを取得
     */
    private function fetchDraftDocuments(int $categoryId, int $userId, int $userBranchId)
    {
        return DocumentVersion::where('category_id', $categoryId)
            ->where('status', 'draft')
            ->where('user_id', $userId)
            ->where('user_branch_id', $userBranchId)
            ->orderBy('id', 'asc')
            ->get();
    }
}

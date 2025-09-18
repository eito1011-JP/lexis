<?php

namespace App\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\User;
use App\Enums\EditStartVersionTargetType;
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

            // アクティブなユーザーブランチが存在しない場合はマージ済みデータを取得
            if (!$activeUserBranch) {
                return $this->fetchMergedNodes($dto->categoryId);
            }

            // user_branch_idを設定
            $userBranchId = $activeUserBranch->id;

            // pull_request_edit_session_tokenが提供されている場合の処理
            $pullRequestEditSessionId = null;
            if ($pullRequestEditSessionToken) {
                $editSession = PullRequestEditSession::with('pullRequest')->where('token', $pullRequestEditSessionToken)->first();
                if ($editSession) {
                    $pullRequestEditSessionId = $editSession->id;
                    $userBranchId = $editSession->pullRequest->user_branch_id;
                }
            }

            // EditStartVersionを使って現在のバージョンを取得
            $categories = $this->fetchCurrentCategories($categoryId, $userBranchId);
            $documents = $this->fetchCurrentDocuments($categoryId, $userBranchId);

            // レスポンス形式に変換
            $formattedCategories = $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'status' => $category->status,
                ];
            });

            $formattedDocuments = $documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
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
     * EditStartVersionを使って現在のカテゴリを取得
     */
    private function fetchCurrentCategories(int $parentId, int $userBranchId)
    {
        // EditStartVersionから現在のバージョンIDを取得
        // 同じoriginal_version_idに対して複数のEditStartVersionがある場合は、最新のものを使用
        $editStartVersions = EditStartVersion::where('user_branch_id', $userBranchId)
            ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('original_version_id')
            ->map(function ($group) {
                return $group->first(); // 最新の（ID最大の）EditStartVersionを取得
            });

        $currentVersionIds = $editStartVersions->pluck('current_version_id')->unique();

        // 現在のバージョンIDに対応するカテゴリを取得
        return DocumentCategory::whereIn('id', $currentVersionIds)
            ->where('parent_id', $parentId)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * EditStartVersionを使って現在のドキュメントを取得
     */
    private function fetchCurrentDocuments(int $categoryId, int $userBranchId)
    {
        // EditStartVersionから現在のバージョンIDを取得
        // 同じoriginal_version_idに対して複数のEditStartVersionがある場合は、最新のものを使用
        $editStartVersions = EditStartVersion::where('user_branch_id', $userBranchId)
            ->where('target_type', EditStartVersionTargetType::DOCUMENT->value)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('original_version_id')
            ->map(function ($group) {
                return $group->first(); // 最新の（ID最大の）EditStartVersionを取得
            });

        $currentVersionIds = $editStartVersions->pluck('current_version_id')->unique();

        // 現在のバージョンIDに対応するドキュメントを取得
        return DocumentVersion::whereIn('id', $currentVersionIds)
            ->where('category_id', $categoryId)
            ->orderBy('id', 'asc')
            ->get();
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
     * マージ済みノード（カテゴリとドキュメント）を取得
     */
    private function fetchMergedNodes(int $categoryId): array
    {
        $categories = $this->fetchMergedCategories($categoryId);
        $documents = $this->fetchMergedDocuments($categoryId);

        return [
            'categories' => $categories,
            'documents' => $documents,
        ];
    }
}

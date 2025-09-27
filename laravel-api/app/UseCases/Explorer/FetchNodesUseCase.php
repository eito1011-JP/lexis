<?php

namespace App\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\User;
use App\Enums\EditStartVersionTargetType;
use App\Enums\DocumentCategoryStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;

class FetchNodesUseCase
{
    /**
     * 指定されたカテゴリに従属するカテゴリとドキュメントを取得
     *
     * @param  FetchNodesDto  $dto  リクエストデータのDTO
     * @param  User  $user  認証済みユーザー
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function execute(FetchNodesDto $dto, User $user): array
    {
            $userBranchId = $this->resolveUserBranchId($user, $dto->pullRequestEditSessionToken);

            if ($userBranchId === null) {
                return $this->fetchMergedNodes($dto->categoryEntityId);
            }

            $categories = $this->fetchCurrentCategories($dto->categoryEntityId, $userBranchId);
            $documents = $this->fetchCurrentDocuments($dto->categoryEntityId, $userBranchId);

            return [
                'categories' => $this->formatCategories($categories),
                'documents' => $this->formatDocuments($documents),
            ];
    }

    /**
     * ユーザーブランチIDを解決する
     *
     * @param  User  $user  認証済みユーザー
     * @param  string|null  $pullRequestEditSessionToken  プルリクエスト編集セッショントークン
     * @return int|null  ユーザーブランチID（nullの場合はマージ済みデータのみ取得）
     */
    private function resolveUserBranchId(User $user, ?string $pullRequestEditSessionToken): ?int
    {
        $activeUserBranch = $user->userBranches()->active()->first();
        
        if (!$activeUserBranch) {
            return null;
        }

        $userBranchId = $activeUserBranch->id;

        if ($pullRequestEditSessionToken) {
            $editSession = PullRequestEditSession::with('pullRequest')
                ->where('token', $pullRequestEditSessionToken)
                ->first();
            
            if ($editSession) {
                $userBranchId = $editSession->pullRequest->user_branch_id;
            }
        }

        return $userBranchId;
    }

    /**
     * EditStartVersionから現在のバージョンIDを取得する
     *
     * @param  EditStartVersionTargetType  $targetType  対象タイプ
     * @param  int  $userBranchId  ユーザーブランチID
     * @return SupportCollection<int, int>  バージョンIDのコレクション
     */
    private function getCurrentVersionIds(EditStartVersionTargetType $targetType, int $userBranchId): SupportCollection
    {
        $currentBranchVersionIds = $this->getLatestEditStartVersions($targetType, $userBranchId)
            ->pluck('current_version_id')
            ->unique();

        $allMergedVersionIds = $this->getLatestEditStartVersions($targetType)
            ->pluck('current_version_id')
            ->unique();

        return $currentBranchVersionIds->merge($allMergedVersionIds)->unique();
    }

    /**
     * 最新のEditStartVersionを取得する
     *
     * @param  EditStartVersionTargetType  $targetType  対象タイプ
     * @param  int|null  $userBranchId  ユーザーブランチID（nullの場合は全ブランチ）
     * @return Collection<EditStartVersion>
     */
    private function getLatestEditStartVersions(EditStartVersionTargetType $targetType, ?int $userBranchId = null): Collection
    {
        $query = EditStartVersion::where('target_type', $targetType->value)
            ->orderBy('id', 'desc');

        if ($userBranchId !== null) {
            $query->where('user_branch_id', $userBranchId);
        }

        return $query->get()
            ->groupBy('original_version_id')
            ->map(function ($group) {
                return $group->first(); // 最新の（ID最大の）EditStartVersionを取得
            });
    }

    /**
     * EditStartVersionを使って現在のカテゴリを取得
     *
     * @param  int  $parentId  親カテゴリID
     * @param  int  $userBranchId  ユーザーブランチID
     * @return Collection<DocumentCategory>
     */
    private function fetchCurrentCategories(int $parentId, int $userBranchId): Collection
    {
        $allVersionIds = $this->getCurrentVersionIds(EditStartVersionTargetType::CATEGORY, $userBranchId);

        return DocumentCategory::whereIn('id', $allVersionIds)
            ->where('parent_entity_id', $parentId)
            ->where(function ($query) use ($userBranchId) {
                $query->where('status', DocumentCategoryStatus::MERGED->value)
                      ->orWhere('user_branch_id', $userBranchId);
            })
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * EditStartVersionを使って現在のドキュメントを取得
     *
     * @param  int  $categoryId  カテゴリID
     * @param  int  $userBranchId  ユーザーブランチID
     * @return Collection<DocumentVersion>
     */
    private function fetchCurrentDocuments(int $categoryId, int $userBranchId): Collection
    {
        $allVersionIds = $this->getCurrentVersionIds(EditStartVersionTargetType::DOCUMENT, $userBranchId);

        return DocumentVersion::whereIn('id', $allVersionIds)
            ->where('category_entity_id', $categoryId)
            ->where(function ($query) use ($userBranchId) {
                $query->where('status', DocumentCategoryStatus::MERGED->value)
                      ->orWhere('user_branch_id', $userBranchId);
            })
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * カテゴリコレクションをレスポンス形式に変換
     *
     * @param  Collection<DocumentCategory>  $categories  カテゴリコレクション
     * @return array<int, array<string, mixed>>
     */
    private function formatCategories(Collection $categories): array
    {
        return $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'entity_id' => $category->entity_id,
                'title' => $category->title,
                'status' => $category->status,
            ];
        })->values()->toArray();
    }

    /**
     * ドキュメントコレクションをレスポンス形式に変換
     *
     * @param  Collection<DocumentVersion>  $documents  ドキュメントコレクション
     * @return array<int, array<string, mixed>>
     */
    private function formatDocuments(Collection $documents): array
    {
        return $documents->map(function ($document) {
            return [
                'id' => $document->id,
                'entity_id' => $document->entity_id,
                'title' => $document->title,
                'status' => $document->status,
                'last_edited_by' => $document->last_edited_by,
            ];
        })->values()->toArray();
    }

    /**
     * マージ済みカテゴリを取得
     *
     * @param  int  $parentId  親カテゴリID
     * @return Collection<DocumentCategory>
     */
    private function fetchMergedCategories(int $parentId): Collection
    {
        return DocumentCategory::where('parent_entity_id', $parentId)
            ->where('status', DocumentCategoryStatus::MERGED->value)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * マージ済みドキュメントを取得
     *
     * @param  int  $categoryId  カテゴリID
     * @return Collection<DocumentVersion>
     */
    private function fetchMergedDocuments(int $categoryId): Collection
    {
        return DocumentVersion::where('category_entity_id', $categoryId)
            ->where('status', DocumentCategoryStatus::MERGED->value)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * マージ済みノード（カテゴリとドキュメント）を取得
     *
     * @param  int  $categoryId  カテゴリID
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function fetchMergedNodes(int $categoryId): array
    {
        $categories = $this->fetchMergedCategories($categoryId);
        $documents = $this->fetchMergedDocuments($categoryId);

        return [
            'categories' => $this->formatCategories($categories),
            'documents' => $this->formatDocuments($documents),
        ];
    }
}

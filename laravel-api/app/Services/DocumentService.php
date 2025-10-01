<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DocumentService
{
    public function __construct(
        private CategoryService $CategoryService
    ) {}

    /**
     * ドキュメントファイルパスを生成
     *
     * @param  string  $categoryPath  カテゴリパス
     * @param  string  $slug  スラッグ
     */
    public function generateDocumentFilePath(string $categoryPath, string $slug): string
    {
        $categoryPath = $categoryPath ? trim($categoryPath, '/') : '';

        return $categoryPath ? "{$categoryPath}/{$slug}.md" : "{$slug}.md";
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
        $childCategories = $this->CategoryService->getChildCategoriesByWorkContext(
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
     * 作業コンテキストに応じて適切なドキュメントを取得
     */
    public function getDocumentByWorkContext(
        int $documentEntityId,
        User $user,
        ?string $pullRequestEditSessionToken = null
    ): ?DocumentVersion {
        // ユーザーのアクティブブランチを取得
        $activeUserBranch = UserBranch::where('user_id', $user->id)->active()->first();

        $baseQuery = DocumentVersion::where('entity_id', $documentEntityId)
            ->where('organization_id', $user->organizationMember->organization_id);

        if (! $activeUserBranch) {
            // アクティブなユーザーブランチがない場合：MERGEDステータスのみ取得
            return $baseQuery->where('status', DocumentStatus::MERGED->value)->first();
        }

        // EditStartVersionから最新の現在のバージョンIDを取得
        // 同じentity_idに対するEditStartVersionチェーンの最新のcurrent_version_idを取得
        $editStartVersions = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
            ->where('target_type', EditStartVersionTargetType::DOCUMENT->value)
            ->whereHas('originalDocumentVersion', function ($query) use ($documentEntityId) {
                $query->where('entity_id', $documentEntityId);
            })
            ->orWhere(function ($query) use ($activeUserBranch, $documentEntityId) {
                $query->where('user_branch_id', $activeUserBranch->id)
                    ->where('target_type', EditStartVersionTargetType::DOCUMENT->value)
                    ->whereHas('currentDocumentVersion', function ($subQuery) use ($documentEntityId) {
                        $subQuery->where('entity_id', $documentEntityId);
                    });
            })
            ->orderBy('id', 'desc')
            ->get();

        if ($editStartVersions->isNotEmpty()) {
            // 最新のEditStartVersionのcurrent_version_idを取得
            $latestEditStartVersion = $editStartVersions->first();
            $document = $baseQuery->where('id', $latestEditStartVersion->current_version_id)->first();
            if ($document) {
                return $document;
            }
        }

        if ($pullRequestEditSessionToken) {
            // 再編集している場合：PUSHEDとDRAFTとMERGEDステータスを取得（最新のものを優先）
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->whereIn('status', [
                        DocumentStatus::PUSHED->value,
                        DocumentStatus::DRAFT->value,
                    ])
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentStatus::MERGED->value);
            })->orderBy('created_at', 'desc')->first();
        } else {
            // 初回編集の場合：DRAFTとMERGEDステータスを取得（最新のものを優先）
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->where('status', DocumentStatus::DRAFT->value)
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentStatus::MERGED->value);
            })->orderBy('created_at', 'desc')->first();
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

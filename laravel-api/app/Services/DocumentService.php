<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Collection;

class DocumentService
{
    public function __construct(
        private CategoryService $CategoryService
    ) {}

    /**
     * 作業コンテキストに応じて配下の全ドキュメントを再帰的に取得
     *
     * @param  int  $categoryEntityId  対象カテゴリのエンティティID
     * @param  User  $user  認証済みユーザー
     * @return Collection ドキュメントバージョンのコレクション
     */
    public function getDescendantDocumentsByWorkContext(
        int $categoryEntityId,
        User $user,
    ): Collection {
        $activeUserBranch = UserBranch::where('user_id', $user->id)->active()->first();
        $organizationId = $user->organizationMember->organization_id;
        $documents = new Collection();

        // 直下のドキュメントを取得
        $directDocuments = $this->getDocumentsByWorkContext(
            $categoryEntityId,
            $organizationId,
            $activeUserBranch,
        );

        $documents = $documents->merge($directDocuments);

        // 子カテゴリ配下のドキュメントを再帰的に取得
        $childCategories = $this->CategoryService->getChildCategoriesByWorkContext(
            $categoryEntityId,
            $organizationId,
            $activeUserBranch,
        );

        foreach ($childCategories as $childCategory) {
            $childDocuments = $this->getDescendantDocumentsByWorkContext(
                $childCategory->entity_id,
                $user,
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

            // 初回編集の場合：DRAFTとMERGEDステータスを取得（最新のものを優先）
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->where('status', DocumentStatus::DRAFT->value)
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentStatus::MERGED->value);
            })->orderBy('created_at', 'desc')->first();
    }

    /**
     * 作業コンテキストに応じて直下のドキュメントを取得
     */
    private function getDocumentsByWorkContext(
        int $categoryEntityId,
        int $organizationId,
        ?UserBranch $activeUserBranch,
    ): Collection {
        $baseQuery = DocumentVersion::where('category_entity_id', $categoryEntityId)
            ->where('organization_id', $organizationId);

        if (! $activeUserBranch) {
            return $baseQuery->where('status', DocumentStatus::MERGED->value)->get();
        }

            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->where('status', DocumentStatus::DRAFT->value)
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentStatus::MERGED->value);
            })->get();
    }
}

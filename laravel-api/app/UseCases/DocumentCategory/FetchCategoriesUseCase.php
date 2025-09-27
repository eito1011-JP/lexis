<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategoryEntity;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Models\UserBranch;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class FetchCategoriesUseCase
{
    /**
     * 認証ユーザーのカテゴリ一覧を取得
     *
     * @param  FetchCategoriesDto  $dto  リクエストDTO
     * @param  User  $user  認証ユーザー
     */
    public function execute(FetchCategoriesDto $dto, User $user): Collection
    {
        // ユーザーのアクティブブランチを取得
        $activeUserBranch = UserBranch::where('user_id', $user->id)->active()->first();

        // 親エンティティIDで対象エンティティを絞り込み
        $parentEntity = DocumentCategoryEntity::where('organization_id', $user->organizationMember->organization_id)->first();

        Log::info('parentEntity: '.json_encode($parentEntity));
        Log::info('dto: '.json_encode($dto));
        if (! $parentEntity) {
            throw new NotFoundException();
        }

        // 必要なresponse
        // document_category_entities.id
        // document_categories.id
        // document_categories.title

        Log::info('activeUserBranch: '.json_encode($activeUserBranch));
        $documentCategories = $parentEntity->documentCategories()->select('id', 'entity_id', 'title')
            ->where('organization_id', $user->organizationMember->organization_id)
            ->when($activeUserBranch, function ($query) use ($activeUserBranch, $dto) {
                // activeなuser_branchがある場合
                $query->when($dto->pullRequestEditSessionToken, function ($subQuery) use ($activeUserBranch) {
                    // 再編集している場合：PUSHEDとDRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
                    $subQuery->where(function ($q) use ($activeUserBranch) {
                        $q->where(function ($q1) use ($activeUserBranch) {
                            $q1->whereIn('status', [
                                DocumentCategoryStatus::PUSHED->value,
                                DocumentCategoryStatus::DRAFT->value,
                            ])
                                ->where('user_branch_id', $activeUserBranch->id);
                        })->orWhere('status', DocumentCategoryStatus::MERGED->value);
                    });
                }, function ($subQuery) use ($activeUserBranch) {
                    // 初回編集の場合：DRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
                    // ただし、編集対象となったカテゴリは除外する（新規作成は除外しない）
                    $editedCategoryIds = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
                        ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
                        ->whereColumn('original_version_id', '!=', 'current_version_id') // 新規作成は除外（original_version_id = current_version_id）
                        ->pluck('original_version_id');

                    $subQuery->where(function ($q) use ($activeUserBranch, $editedCategoryIds) {
                        $q->where(function ($q1) use ($activeUserBranch, $editedCategoryIds) {
                            $q1->where('status', DocumentCategoryStatus::DRAFT->value)
                                ->where('user_branch_id', $activeUserBranch->id)
                                ->whereNotIn('id', $editedCategoryIds);
                        })->orWhere(function ($q2) use ($editedCategoryIds) {
                            $q2->where('status', DocumentCategoryStatus::MERGED->value)
                                ->whereNotIn('id', $editedCategoryIds);
                        });
                    });
                });
            }, function ($query) {
                // 未編集の場合：MERGEDステータスのみ取得
                $query->where('status', DocumentCategoryStatus::MERGED->value);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return $documentCategories;

        // エンティティごとに最新のカテゴリバージョンを取得するクエリを構築
        // $query = DocumentCategory::select('id', 'entity_id', 'title')
        //     ->whereIn('entity_id', $targetEntityIds)
        //     ->where('parent_entity_id', $dto->parentEntityId)
        //     ->where('organization_id', $user->organizationMember->organization_id);

        // if ($activeUserBranch) {
        //     // activeなuser_branchがある場合
        //     if ($dto->pullRequestEditSessionToken) {
        //         // 再編集している場合：PUSHEDとDRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
        //         $query->where(function ($subQuery) use ($activeUserBranch) {
        //             $subQuery->where(function ($q1) use ($activeUserBranch) {
        //                 $q1->whereIn('status', [
        //                     DocumentCategoryStatus::PUSHED->value,
        //                     DocumentCategoryStatus::DRAFT->value,
        //                 ])
        //                     ->where('user_branch_id', $activeUserBranch->id);
        //             })->orWhere('status', DocumentCategoryStatus::MERGED->value);
        //         });
        //     } else {
        //         // 初回編集の場合：DRAFTステータス（自分のユーザーブランチのもの）とMERGEDステータスを取得
        //         // ただし、編集対象となったカテゴリは除外する（新規作成は除外しない）
        //         $editedCategoryIds = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
        //             ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
        //             ->whereColumn('original_version_id', '!=', 'current_version_id') // 新規作成は除外（original_version_id = current_version_id）
        //             ->pluck('original_version_id');

        //         $query->where(function ($subQuery) use ($activeUserBranch, $editedCategoryIds) {
        //             $subQuery->where(function ($q1) use ($activeUserBranch, $editedCategoryIds) {
        //                 $q1->where('status', DocumentCategoryStatus::DRAFT->value)
        //                     ->where('user_branch_id', $activeUserBranch->id)
        //                     ->whereNotIn('id', $editedCategoryIds);
        //             })->orWhere(function ($q2) use ($editedCategoryIds) {
        //                 $q2->where('status', DocumentCategoryStatus::MERGED->value)
        //                     ->whereNotIn('id', $editedCategoryIds);
        //             });
        //         });
        //     }
        // } else {
        //     // 未編集の場合：MERGEDステータスのみ取得
        //     $query->where('status', DocumentCategoryStatus::MERGED->value);
        // }

        // // ステータス優先度とcreated_atで並び替え
        // // PUSHEDが最初、DRAFTが後、同一ステータス内ではcreated_at昇順
        // return $query->orderBy('created_at', 'asc')->get();
    }
}

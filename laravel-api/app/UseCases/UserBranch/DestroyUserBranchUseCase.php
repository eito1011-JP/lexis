<?php

namespace App\UseCases\UserBranch;

use App\Dto\UseCase\UserBranch\DestroyUserBranchDto;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Models\CategoryEntity;
use App\Models\CategoryVersion;
use App\Models\DocumentEntity;
use App\Models\DocumentVersion;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ユーザーブランチ削除のユースケース
 */
class DestroyUserBranchUseCase
{
    /**
     * ユーザーブランチを物理削除
     *
     * @param DestroyUserBranchDto $dto DTO
     * @return array{is_success: bool} 削除結果
     *
     * @throws NotFoundException ユーザーブランチが見つからない場合
     */
    public function execute(DestroyUserBranchDto $dto): array
    {
        try {
            $activeUserBranch = $dto->user->userBranches()->where('id', $dto->userBranchId)->active()->first();

            if (! $activeUserBranch) {
                throw new NotFoundException();
            }

            $branchId = $activeUserBranch->id;
            $orgId = $activeUserBranch->organization_id;

            DB::transaction(function () use ($activeUserBranch, $branchId, $orgId) {
                DocumentVersion::where('user_branch_id', $branchId)
                    ->whereIn('status', [DocumentStatus::DRAFT->value, DocumentStatus::PUSHED->value])
                    ->forceDelete();

                CategoryVersion::where('user_branch_id', $branchId)
                    ->whereIn('status', [DocumentCategoryStatus::DRAFT->value, DocumentCategoryStatus::PUSHED->value])
                    ->forceDelete();

                DocumentEntity::where('organization_id', $orgId)
                    ->whereDoesntHave('documentVersions', function ($query) {
                        $query->withTrashed();
                    })
                    ->forceDelete();

                CategoryEntity::where('organization_id', $orgId)
                    ->whereDoesntHave('categoryVersions', function ($query) {
                        $query->withTrashed();
                    })
                    ->forceDelete();

                $activeUserBranch->forceDelete();
            });

            return [
                'is_success' => true,
            ];
        } catch (Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}


<?php

namespace App\UseCases\Document;

use App\Consts\Flag;
use App\Dto\UseCase\Document\DestroyDocumentDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\DocumentEntity;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ドキュメント削除のユースケース
 */
class DestroyDocumentUseCase
{
    public function __construct(
        private UserBranchService $userBranchService,
        private DocumentService $documentService,
    ) {}

    /**
     * ドキュメントを削除
     *
     * @param  DestroyDocumentDto  $dto  ドキュメント削除DTO
     * @param  User  $user  認証済みユーザー
     * @return DocumentVersion
     *
     * @throws NotFoundException 組織またはドキュメントが見つからない場合
     */
    public function execute(DestroyDocumentDto $dto, User $user): DocumentVersion
    {
        try {
            DB::beginTransaction();

            // 1. 組織メンバー確認
            if (! $user->organizationMember) {
                throw new NotFoundException;
            }
            
            $organizationId = $user->organizationMember->organization_id;

            // 2. 組織が存在しない場合はエラー
            if (! $organizationId) {
                throw new NotFoundException;
            }

            // 3. DocumentEntityの存在確認
            $documentEntity = DocumentEntity::find($dto->document_entity_id);

            if (! $documentEntity) {
                throw new NotFoundException;
            }

            // 4. fetchOrCreateActiveBranch
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $organizationId,
            );

            // 6. 編集対象のexistingDocumentを取得
            $existingDocument = $this->documentService->getDocumentByWorkContext(
                $dto->document_entity_id,
                $user,
            );

            if (! $existingDocument) {
                throw new NotFoundException;
            }

            // 7. DocumentVersionを作成（削除用）
            $newDocumentVersion = DocumentVersion::create([
                'entity_id' => $documentEntity->id,
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'status' => DocumentStatus::DRAFT->value,
                'description' => $existingDocument->description,
                'category_entity_id' => $existingDocument->category_entity_id,
                'title' => $existingDocument->title,
                'deleted_at' => now(),
                'is_deleted' => Flag::TRUE,
            ]);

            // 8. EditStartVersionを作成
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                'entity_id' => $documentEntity->id,
                'original_version_id' => $existingDocument->id,
                'current_version_id' => $newDocumentVersion->id,
            ]);

            // 9. 既存ドキュメントがDRAFTステータスの場合は削除
            if ($existingDocument->status === DocumentStatus::DRAFT->value) {
                $existingDocument->delete();
            }

            DB::commit();

            return $newDocumentVersion;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw $e;
        }
    }
}

<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\UpdateDocumentDto;
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
 * ドキュメント更新のユースケース
 */
class UpdateDocumentUseCase
{
    public function __construct(
        private UserBranchService $userBranchService,
        private DocumentService $documentService,
    ) {}

    /**
     * ドキュメントを更新
     *
     * @param  UpdateDocumentDto  $dto  ドキュメント更新DTO
     * @param  User  $user  認証済みユーザー
     * @return DocumentVersion 更新されたドキュメント
     *
     * @throws NotFoundException 組織またはドキュメントが見つからない場合
     */
    public function execute(UpdateDocumentDto $dto, User $user): DocumentVersion
    {
        try {
            DB::beginTransaction();

            // 1. $organizationId = $user->organizationMember->organization_id;
            $organizationId = $user->organizationMember->organization_id;

            // 2. if $organizationない場合 throw new NotFoundException;
            if (! $organizationId) {
                throw new NotFoundException;
            }

            $documentEntity = DocumentEntity::find($dto->document_entity_id);

            if (! $documentEntity) {
                throw new NotFoundException;
            }

            // 3. fetchOrCreateActiveBranch
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $organizationId,
            );

            // 5. 編集対象のexistingDocumentを取得
            $existingDocument = $this->documentService->getDocumentByWorkContext(
                $dto->document_entity_id,
                $user,
            );

            // 7. DocumentVersionを作成
            $newDocumentVersion = DocumentVersion::create([
                'entity_id' => $documentEntity->id,
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'status' => DocumentStatus::DRAFT->value,
                'description' => $dto->description,
                'category_entity_id' => $existingDocument->category_entity_id,
                'title' => $dto->title,
                'last_edited_by' => $user->email,
            ]);

            // 8. EditStartVersionを作成(original_version_id = existingDocument.id, current_version_id = 新規のDocumentVersion.id)
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                'entity_id' => $documentEntity->id,
                'original_version_id' => $existingDocument->id,
                'current_version_id' => $newDocumentVersion->id,
            ]);

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

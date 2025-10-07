<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\CreateDocumentUseCaseDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\DocumentEntity;
use App\Models\EditStartVersion;
use App\Services\CategoryService;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateDocumentUseCase
{
    public function __construct(
        private DocumentService $documentService,
        private UserBranchService $userBranchService,
        private CategoryService $CategoryService
    ) {}

    /**
     * ドキュメントを作成
     *
     * @param  CreateDocumentUseCaseDto  $dto  DTOオブジェクト
     * @return DocumentVersion 作成されたドキュメント
     *
     * @throws NotFoundException 組織が見つからない場合
     * @throws \Exception その他のエラーが発生した場合
     */
    public function execute(CreateDocumentUseCaseDto $dto): DocumentVersion
    {
        try {
            DB::beginTransaction();
            // ユーザーの組織IDを取得
            $organizationId = $dto->user->organizationMember?->organization_id;
            if (! $organizationId) {
                throw new NotFoundException;
            }

            // ユーザーブランチIDを取得または作成
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $dto->user,
                $organizationId,
            );


            // ドキュメントエンティティを作成
            $documentEntity = DocumentEntity::create([
                'organization_id' => $organizationId,
            ]);

            // ドキュメントを作成
            $document = DocumentVersion::create([
                'entity_id' => $documentEntity->id,
                'user_id' => $dto->user->id,
                'user_branch_id' => $userBranchId,
                'organization_id' => $organizationId,
                'category_entity_id' => $dto->categoryEntityId,
                'title' => $dto->title,
                'description' => $dto->description,
                'status' => DocumentStatus::DRAFT->value,
                'last_edited_by' => $dto->user->email,
            ]);

            // EditStartVersionを作成
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                'original_version_id' => $document->id,
                'current_version_id' => $document->id,
            ]);

            DB::commit();

            return $document;
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollBack();

            throw $e;
        }
    }
}

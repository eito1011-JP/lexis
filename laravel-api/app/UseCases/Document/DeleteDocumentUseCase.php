<?php

namespace App\UseCases\Document;

use App\Consts\Flag;
use App\Dto\UseCase\Document\DeleteDocumentDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\DocumentVersionEntity;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ドキュメント削除のユースケース
 */
class DeleteDocumentUseCase
{
    public function __construct(
        private UserBranchService $userBranchService,
        private DocumentService $documentService,
    ) {}

    /**
     * ドキュメントを削除
     *
     * @param  DeleteDocumentDto  $dto  ドキュメント削除DTO
     * @param  User  $user  認証済みユーザー
     * @return array{success: bool, error?: string}
     *
     * @throws NotFoundException 組織またはドキュメントが見つからない場合
     */
    public function execute(DeleteDocumentDto $dto, User $user): array
    {
        try {
            DB::beginTransaction();

            // 1. 組織メンバー確認
            $organizationId = $user->organizationMember->organization_id;

            // 2. 組織が存在しない場合はエラー
            if (! $organizationId) {
                throw new NotFoundException;
            }

            // 3. DocumentVersionEntityの存在確認
            $documentEntity = DocumentVersionEntity::find($dto->document_entity_id);

            if (! $documentEntity) {
                throw new NotFoundException;
            }

            // 4. fetchOrCreateActiveBranch
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $organizationId,
                $dto->edit_pull_request_id
            );

            // 5. PullRequestEditSession::findEditSessionId
            $pullRequestEditSessionId = PullRequestEditSession::findEditSessionId(
                $dto->edit_pull_request_id,
                $dto->pull_request_edit_token,
                $user->id
            );

            // 6. 編集対象のexistingDocumentを取得
            $existingDocument = $this->documentService->getDocumentByWorkContext(
                $dto->document_entity_id,
                $user,
                $dto->pull_request_edit_token
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
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                'status' => DocumentStatus::DRAFT->value,
                'description' => $existingDocument->description,
                'category_entity_id' => $existingDocument->category_entity_id,
                'title' => $existingDocument->title,
                'last_edited_by' => $user->email,
                'deleted_at' => now(),
                'is_deleted' => Flag::TRUE,
            ]);

            // 8. EditStartVersionを作成
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                'original_version_id' => $existingDocument->id,
                'current_version_id' => $newDocumentVersion->id,
            ]);

            // 9. 既存ドキュメントがDRAFTステータスの場合は削除
            if ($existingDocument->status === DocumentStatus::DRAFT->value) {
                $existingDocument->delete();
            }

            // 10. プルリクエストを編集している処理を考慮
            if ($pullRequestEditSessionId) {
                PullRequestEditSessionDiff::updateOrCreate(
                    [
                        'pull_request_edit_session_id' => $pullRequestEditSessionId,
                        'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                        'original_version_id' => $existingDocument->id,
                    ],
                    [
                        'current_version_id' => $newDocumentVersion->id,
                        'diff_type' => 'deleted',
                    ]
                );
            }

            DB::commit();

            return [
                'success' => true,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw $e;
        }
    }
}

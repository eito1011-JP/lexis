<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestEditSessionDiffType;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Models\User;
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

            // 3. fetchOrCreateActiveBranch
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $organizationId,
                $dto->edit_pull_request_id
            );

            // 4. PullRequestEditSession::findEditSessionId
            $pullRequestEditSessionId = PullRequestEditSession::findEditSessionId(
                $dto->edit_pull_request_id,
                $dto->pull_request_edit_token,
                $user->id
            );

            // 5. 編集対象のexistingDocumentを取得
            $existingDocument = DocumentVersion::find($dto->document_entity_id);
            $existingDocumentEntity = $existingDocument->entity;

            // 6. if existingDocumentがない場合 throw new NotFoundException;
            if (! $existingDocument || ! $existingDocumentEntity) {
                throw new NotFoundException;
            }

            // 7. DocumentVersionを作成
            $newDocumentVersion = DocumentVersion::create([
                'entity_id' => $existingDocumentEntity->id,
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
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
                'original_version_id' => $existingDocument->id,
                'current_version_id' => $newDocumentVersion->id,
            ]);

            if ($existingDocument->status === DocumentStatus::DRAFT->value) {
                $existingDocument->delete();
            }

            // 9. プルリクエストを編集している処理を考慮
            if ($pullRequestEditSessionId) {
                PullRequestEditSessionDiff::updateOrCreate(
                    [
                        'pull_request_edit_session_id' => $pullRequestEditSessionId,
                        'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                        'original_version_id' => $existingDocument->id,
                    ],
                    [
                        'current_version_id' => $newDocumentVersion->id,
                        'diff_type' => 'updated',
                    ]
                );
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

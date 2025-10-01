<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\CreateDocumentUseCaseDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestEditSessionDiffType;
use App\Models\DocumentVersion;
use App\Models\DocumentEntity;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
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
                $dto->editPullRequestId
            );

            // プルリクエスト編集セッションIDを取得
            $pullRequestEditSessionId = null;
            if (! empty($dto->editPullRequestId) && ! empty($dto->pullRequestEditToken)) {
                $pullRequestEditSessionId = PullRequestEditSession::findEditSessionId(
                    $dto->editPullRequestId,
                    $dto->pullRequestEditToken,
                    $dto->user->id
                );
            }

            // ドキュメントエンティティを作成
            $documentEntity = DocumentEntity::create([
                'organization_id' => $organizationId,
            ]);

            // ドキュメントを作成
            $document = DocumentVersion::create([
                'entity_id' => $documentEntity->id,
                'user_id' => $dto->user->id,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
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

            // プルリクエスト編集セッション差分の処理
            if ($pullRequestEditSessionId) {
                PullRequestEditSessionDiff::updateOrCreate(
                    [
                        'pull_request_edit_session_id' => $pullRequestEditSessionId,
                        'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                        'current_version_id' => $document->id,
                    ],
                    [
                        'current_version_id' => $document->id,
                        'diff_type' => PullRequestEditSessionDiffType::CREATED->value,
                    ]
                );
            }

            DB::commit();

            return $document;
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollBack();

            throw $e;
        }
    }
}

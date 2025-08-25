<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestEditSessionDiffType;
use App\Exceptions\TargetDocumentNotFoundException;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSessionDiff;
use App\Services\DocumentCategoryService;
use App\Services\DocumentDiffService;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use App\Services\VersionEditPermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateDocumentUseCase
{
    public function __construct(
        private DocumentService $documentService,
        private UserBranchService $userBranchService,
        private DocumentCategoryService $documentCategoryService,
        private DocumentDiffService $documentDiffService,
        private VersionEditPermissionService $versionEditPermissionService
    ) {}

    /**
     * ドキュメントを更新
     *
     * @param  UpdateDocumentDto  $dto  ドキュメント更新用DTO
     * @param  object  $user  認証済みユーザー
     */
    public function execute(UpdateDocumentDto $dto, object $user): array
    {
        try {
            return DB::transaction(function () use ($dto, $user) {
                try {
                    // 編集前のdocumentのIdからexistingDocumentを取得
                    $existingDocument = DocumentVersion::find($dto->current_document_id);

                    if (! $existingDocument) {
                        throw new TargetDocumentNotFoundException('編集対象のドキュメントが見つかりません');
                    }

                    // category_pathからcategoryIdを取得（file_order処理用）
                    $categoryId = $this->documentCategoryService->getIdFromPath($dto->category_path);

                    // アクティブブランチを取得
                    $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                        $user,
                        $dto->edit_pull_request_id ?? null
                    );

                    // 編集権限チェック
                    $permissionResult = $this->versionEditPermissionService->hasEditPermission(
                        $existingDocument,
                        $userBranchId,
                        $user,
                        $dto->edit_pull_request_id,
                        $dto->pull_request_edit_token
                    );

                    $hasReEditSession = $permissionResult['has_re_edit_session'];
                    $pullRequestEditSessionId = $hasReEditSession ? $permissionResult['pull_request_edit_session_id'] : null;

                    // 差分なしであれば、何もしない
                    if (!$this->documentDiffService->hasDocumentChanges($dto, $existingDocument)) {
                        return [
                            'result' => 'no_changes_exist',
                        ];
                    }

                    // file_orderの処理
                    $finalFileOrder = $this->documentService->updateFileOrder(
                        $dto->file_order,
                        $existingDocument->file_order,
                        $categoryId,
                        $userBranchId,
                        $dto->edit_pull_request_id ?? null,
                        $existingDocument->id,
                        $user->id,
                        $user->email
                    );

                    // draftドキュメントのみ論理削除
                    $shouldSoftDeleteExisting = $existingDocument->status === DocumentStatus::DRAFT->value;
                    if ($shouldSoftDeleteExisting) {
                        $existingDocument->delete();
                    }

                    // 新しいドキュメントバージョンを作成
                    $newDocumentVersion = DocumentVersion::create([
                        'user_id' => $user->id,
                        'user_branch_id' => $userBranchId,
                        'pull_request_edit_session_id' => $pullRequestEditSessionId,
                        'file_path' => $existingDocument->file_path,
                        'status' => DocumentStatus::DRAFT->value,
                        'content' => $dto->content,
                        'slug' => $dto->slug,
                        'sidebar_label' => $dto->sidebar_label,
                        'file_order' => $finalFileOrder,
                        'last_edited_by' => $user->email,
                        'is_public' => $dto->is_public,
                        'category_id' => $categoryId,
                    ]);

                    // 編集開始バージョンを記録
                    EditStartVersion::create([
                        'user_branch_id' => $userBranchId,
                        'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                        'original_version_id' => $existingDocument->id,
                        'current_version_id' => $newDocumentVersion->id,
                    ]);

                    // プルリクエスト編集セッション差分の処理
                    if ($hasReEditSession) {
                        PullRequestEditSessionDiff::updateOrCreate(
                            [
                                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                                'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                                'original_version_id' => $existingDocument->id,
                            ],
                            [
                                'current_version_id' => $newDocumentVersion->id,
                                'diff_type' => PullRequestEditSessionDiffType::UPDATED->value,
                            ]
                        );
                    }

                    return [
                        'result' => 'successfully_updated',
                        'document_version' => $newDocumentVersion,
                    ];
                } catch (\Exception $e) {
                    Log::error('UpdateDocumentUseCase: トランザクション内でエラーが発生しました。データベースの変更はロールバックされます。', [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id ?? null,
                        'current_document_id' => $dto->current_document_id ?? null,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    throw $e;
                }
            });
        } catch (TargetDocumentNotFoundException $e) {
            // 特定の例外は再スロー
            throw $e;
        } catch (\Exception $e) {
            Log::error('UpdateDocumentUseCase: 予期しないエラーが発生しました', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

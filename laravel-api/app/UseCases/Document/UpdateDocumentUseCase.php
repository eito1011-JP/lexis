<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestEditSessionDiffType;
use App\Exceptions\TargetDocumentNotFoundException;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Services\DocumentCategoryService;
use App\Services\DocumentDiffService;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use Illuminate\Support\Facades\Log;

class UpdateDocumentUseCase
{
    public function __construct(
        private DocumentService $documentService,
        private UserBranchService $userBranchService,
        private DocumentCategoryService $documentCategoryService,
        private DocumentDiffService $documentDiffService
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

            // 編集セッションIDを取得
            $pullRequestEditSessionId = null;
            $isEditSessionSpecified = ! empty($dto->edit_pull_request_id) && ! empty($dto->pull_request_edit_token);
            if ($isEditSessionSpecified) {
                $pullRequestEditSessionId = PullRequestEditSession::findEditSessionId(
                    $dto->edit_pull_request_id,
                    $dto->pull_request_edit_token,
                    $user->id
                );

                // 指定された編集セッションが無効
                if (empty($pullRequestEditSessionId)) {
                    throw new \InvalidArgumentException('無効な編集セッションです');
                }
            }

            // 編集権限チェック
            if (!$this->documentDiffService->canEditDocument($existingDocument, $userBranchId, $pullRequestEditSessionId)) {
                throw new \InvalidArgumentException('他のユーザーの未マージドキュメントは編集できません');
            }

            // 差分なしであれば何もしない
            if (!$this->documentDiffService->hasDocumentChanges($dto, $existingDocument)) {
                return [
                    'result' => 'no_changes_exist',
                ];
            }

            // file_orderの処理
            $categoryId = $existingDocument->category_id;
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

            // 既存ドキュメントのソフトデリート条件
            $shouldSoftDeleteExisting = false;
            if ($pullRequestEditSessionId) {
                // 編集セッション中は DRAFT のみ論理削除
                $shouldSoftDeleteExisting = $existingDocument->status === DocumentStatus::DRAFT->value;
            } else {
                // 通常編集では DRAFT/PUSHED は論理削除、MERGED は保持
                $shouldSoftDeleteExisting = in_array($existingDocument->status, [
                    DocumentStatus::DRAFT->value,
                    DocumentStatus::PUSHED->value,
                ], true);
            }

            if ($shouldSoftDeleteExisting) {
                // プロジェクトのアサーション基準に合わせ、deleted_at のみを更新
                // （is_deleted は更新しない）
                $existingDocument->deleted_at = now();
                $existingDocument->save();
            }

            // 新しいドキュメントバージョンを作成
            $newDocumentVersion = DocumentVersion::create([
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId ?? null,
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
            if ($pullRequestEditSessionId) {
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
        } catch (TargetDocumentNotFoundException $e) {
            // ビジネスロジックエラーはそのまま再スロー
            throw $e;
        } catch (\Exception $e) {
            Log::error('UpdateDocumentUseCase: エラー', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

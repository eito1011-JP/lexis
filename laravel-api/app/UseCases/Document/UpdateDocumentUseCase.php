<?php

namespace App\UseCases\Document;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSessionDiff;
use App\Services\DocumentService;
use App\Services\PullRequestEditSessionService;
use App\Services\UserBranchService;
use Illuminate\Support\Facades\Log;

class UpdateDocumentUseCase
{
    public function __construct(
        private DocumentService $documentService,
        private UserBranchService $userBranchService,
        private PullRequestEditSessionService $pullRequestEditSessionService
    ) {}

    /**
     * ドキュメントを更新
     *
     * @param  array  $requestData  リクエストデータ
     * @param  object  $user  認証済みユーザー
     * @return array{success: bool, document?: object, error?: string}
     */
    public function execute(array $requestData, object $user): array
    {
        try {
            // 編集前のdocumentのIdからexistingDocumentを取得
            $existingDocument = DocumentVersion::find($requestData['current_document_id']);

            if (! $existingDocument) {
                return [
                    'success' => false,
                    'error' => '編集対象のドキュメントが見つかりません',
                ];
            }

            // パスからslugとcategoryPathを取得（file_order処理用）
            $pathInfo = $this->documentService->parseCategoryPathWithSlug($requestData['category_path_with_slug']);
            $categoryId = $pathInfo['categoryId'];

            // アクティブブランチを取得
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $requestData['edit_pull_request_id'] ?? null
            );

            // 編集セッションIDを取得
            $pullRequestEditSessionId = null;
            if (! empty($requestData['edit_pull_request_id']) && ! empty($requestData['pull_request_edit_token'])) {
                $pullRequestEditSessionId = $this->pullRequestEditSessionService->getPullRequestEditSessionId(
                    $requestData['edit_pull_request_id'],
                    $requestData['pull_request_edit_token'],
                    $user->id
                );
            }

            // file_orderの処理
            $categoryId = $existingDocument->category_id;
            $finalFileOrder = $this->documentService->processFileOrder(
                $requestData['file_order'],
                $categoryId,
                $existingDocument->file_order,
                $userBranchId,
                $existingDocument->id
            );

            // 既存ドキュメントは論理削除せず、新しいドキュメントバージョンを作成
            $newDocumentVersion = DocumentVersion::create([
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId ?? null,
                'file_path' => $existingDocument->file_path,
                'status' => DocumentStatus::DRAFT->value,
                'content' => $requestData['content'],
                'slug' => $requestData['slug'],
                'sidebar_label' => $requestData['sidebar_label'],
                'file_order' => $finalFileOrder,
                'last_edited_by' => $user->email,
                'is_public' => $requestData['is_public'],
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
                        'diff_type' => 'updated',
                    ]
                );
            }

            return [
                'success' => true,
                'document' => $newDocumentVersion,
            ];

        } catch (\Exception $e) {
            Log::error('UpdateDocumentUseCase: エラー', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'ドキュメントの更新に失敗しました',
            ];
        }
    }
}

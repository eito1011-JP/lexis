<?php

namespace App\UseCases\Document;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSessionDiff;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use App\Services\PullRequestEditSessionService;
use App\Services\UserBranchService;
use Illuminate\Support\Facades\Log;

class CreateDocumentUseCase
{
    public function __construct(
        private DocumentService $documentService,
        private UserBranchService $userBranchService,
        private PullRequestEditSessionService $pullRequestEditSessionService,
        private DocumentCategoryService $documentCategoryService
    ) {}

    /**
     * ドキュメントを作成
     *
     * @param  array  $requestData  リクエストデータ
     *                              - category_path: string カテゴリパス（例: 'tutorial/basics'）
     *                              - slug: string ドキュメントのスラッグ
     *                              - sidebar_label: string サイドバーに表示されるラベル
     *                              - content: string ドキュメントの内容（Markdown形式）
     *                              - file_order: int|null ファイルの表示順序
     *                              - is_public: bool 公開フラグ
     *                              - edit_pull_request_id: int|null 編集対象のプルリクエストID
     *                              - pull_request_edit_token: string|null プルリクエスト編集トークン
     * @param  object  $user  認証済みユーザー
     * @return array{success: bool, document?: object, error?: string}
     */
    public function execute(array $requestData, object $user): array
    {
        try {
            // ユーザーブランチIDを取得または作成
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $requestData['edit_pull_request_id'] ?? null
            );

            // プルリクエスト編集セッションIDを取得
            $pullRequestEditSessionId = null;
            if (! empty($requestData['edit_pull_request_id']) && ! empty($requestData['pull_request_edit_token'])) {
                $pullRequestEditSessionId = $this->pullRequestEditSessionService->getPullRequestEditSessionId(
                    $requestData['edit_pull_request_id'],
                    $requestData['pull_request_edit_token'],
                    $user->id
                );
            }

            // カテゴリパスからカテゴリIDを取得
            $categoryPath = array_filter(explode('/', $requestData['category_path'] ?? ''));
            $categoryId = $this->documentCategoryService->getIdFromPath(implode('/', $categoryPath));

            // file_orderの重複処理・自動採番
            $correctedFileOrder = $this->documentService->normalizeFileOrder(
                $requestData['file_order'] ? (int) $requestData['file_order'] : null,
                $categoryId ?? null
            );

            // ファイルパスの生成
            $filePath = $this->documentService->generateDocumentFilePath(
                $requestData['category_path'] ?? '',
                $requestData['slug'] ?? ''
            );

            // ドキュメントを作成
            $document = DocumentVersion::create([
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                'category_id' => $categoryId,
                'sidebar_label' => $requestData['sidebar_label'] ?? '',
                'slug' => $requestData['slug'] ?? '',
                'content' => $requestData['content'] ?? '',
                'is_public' => $requestData['is_public'] ?? false,
                'status' => DocumentStatus::DRAFT->value,
                'last_edited_by' => $user->email,
                'file_order' => $correctedFileOrder,
                'file_path' => $filePath,
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
                        'diff_type' => 'created',
                    ]
                );
            }

            return [
                'success' => true,
                'document' => $document,
            ];

        } catch (\Exception $e) {
            Log::error('CreateDocumentUseCase: エラー', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return [
                'success' => false,
                'error' => 'ドキュメントの作成に失敗しました',
            ];
        }
    }
}

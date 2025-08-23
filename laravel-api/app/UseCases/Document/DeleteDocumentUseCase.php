<?php

namespace App\UseCases\Document;

use App\Consts\Flag;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Services\DocumentCategoryService;
use App\Services\UserBranchService;
use Illuminate\Support\Facades\Log;

class DeleteDocumentUseCase
{
    public function __construct(
        private UserBranchService $userBranchService,
        private DocumentCategoryService $documentCategoryService
    ) {}

    /**
     * ドキュメントを削除
     *
     * @param  array  $requestData  リクエストデータ
     *                              - category_path_with_slug: string カテゴリパスとスラッグ（例: 'tutorial/basics/my-document'）
     *                              - edit_pull_request_id: int|null 編集対象のプルリクエストID
     *                              - pull_request_edit_token: string|null プルリクエスト編集トークン
     * @param  object  $user  認証済みユーザー
     * @return array{success: bool, error?: string}
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
                $pullRequestEditSessionId = PullRequestEditSession::findEditSessionId(
                    $requestData['edit_pull_request_id'],
                    $requestData['pull_request_edit_token'],
                    $user->id
                );
            }

            // パスからスラッグとカテゴリパスを分離
            $pathParts = array_filter(explode('/', $requestData['category_path_with_slug']));
            $slug = array_pop($pathParts);
            $categoryPath = implode('/', $pathParts);

            // カテゴリパスからカテゴリIDを取得
            $categoryId = $this->documentCategoryService->getIdFromPath($categoryPath);

            // 削除対象のドキュメントを取得
            $existingDocument = DocumentVersion::where('category_id', $categoryId)
                ->where('slug', $slug)
                ->first();

            if (! $existingDocument) {
                return [
                    'success' => false,
                    'error' => '削除対象のドキュメントが見つかりません',
                ];
            }

            // 既存ドキュメントは論理削除せず、新しいdraftステータスのドキュメントを作成（is_deleted = 1）
            $newDocumentVersion = DocumentVersion::create([
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'file_path' => $existingDocument->file_path,
                'status' => DocumentStatus::DRAFT->value,
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                'content' => $existingDocument->content,
                'slug' => $existingDocument->slug,
                'sidebar_label' => $existingDocument->sidebar_label,
                'file_order' => $existingDocument->file_order,
                'last_edited_by' => $user->email,
                'is_public' => $existingDocument->is_public,
                'category_id' => $existingDocument->category_id,
                'deleted_at' => now(),
                'is_deleted' => Flag::TRUE,
            ]);

            // EditStartVersionを作成
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
                        'current_version_id' => $existingDocument->id,
                    ],
                    [
                        'current_version_id' => $newDocumentVersion->id,
                        'diff_type' => 'deleted',
                    ]
                );
            }

            return [
                'success' => true,
            ];

        } catch (\Exception $e) {
            Log::error('DeleteDocumentUseCase: エラー', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'ドキュメントの削除に失敗しました',
            ];
        }
    }
}

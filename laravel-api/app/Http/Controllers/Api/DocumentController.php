<?php

namespace App\Http\Controllers\Api;

use App\Consts\Flag;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Http\Requests\Api\Document\CreateDocumentRequest;
use App\Http\Requests\Api\Document\DeleteDocumentRequest;
use App\Http\Requests\Api\Document\GetDocumentDetailRequest;
use App\Http\Requests\Api\Document\GetDocumentsRequest;
use App\Http\Requests\Api\Document\UpdateDocumentRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequest;
use App\Models\PullRequestEditSessionDiff;
use App\Services\DocumentService;
use App\Services\PullRequestEditSessionService;
use App\Services\UserBranchService;
use App\UseCases\Document\CreateDocumentUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentController extends ApiBaseController
{
    protected DocumentService $documentService;

    protected UserBranchService $userBranchService;

    protected CreateDocumentUseCase $createDocumentUseCase;

    protected PullRequestEditSessionService $pullRequestEditSessionService;

    public function __construct(
        DocumentService $documentService,
        UserBranchService $userBranchService,
        CreateDocumentUseCase $createDocumentUseCase,
        PullRequestEditSessionService $pullRequestEditSessionService
    ) {
        $this->documentService = $documentService;
        $this->userBranchService = $userBranchService;
        $this->createDocumentUseCase = $createDocumentUseCase;
        $this->pullRequestEditSessionService = $pullRequestEditSessionService;
    }

    /**
     * カテゴリ一覧を取得
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $categories = DocumentCategory::select('id', 'name', 'slug', 'sidebar_label', 'position', 'description')
                ->orderBy('position')
                ->get();

            return response()->json([
                'categories' => $categories,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリ一覧の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ドキュメント一覧を取得
     */
    public function getDocuments(GetDocumentsRequest $request): JsonResponse
    {
        try {
            // 認証チェック（新しいメソッドを使用）
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            $categoryPath = array_filter(explode('/', $request->category_path));

            // カテゴリIDを取得（パスから）
            $parentId = DocumentCategory::getIdFromPath($categoryPath);

            $userBranchId = $user->userBranches()->active()->orderBy('id', 'desc')->first()->id ?? null;

            Log::info('userBranchId: '.$userBranchId);
            // edit_pull_request_idが存在する場合、プルリクエストからuser_branch_idを取得
            if ($request->edit_pull_request_id) {
                $pullRequest = PullRequest::find($request->edit_pull_request_id);
                $userBranchId = $pullRequest?->user_branch_id ?? null;
            }

            // サブカテゴリを取得
            $subCategories = DocumentCategory::getSubCategories($parentId, $userBranchId, $request->edit_pull_request_id);

            // ドキュメントを取得
            $documents = DocumentVersion::getDocumentsByCategoryId($parentId, $userBranchId, $request->edit_pull_request_id);

            // ソート処理
            $sortedDocuments = $documents
                ->filter(function ($doc) {
                    return $doc->file_order !== null;
                })
                ->sortBy('file_order')
                ->map(function ($doc) {
                    return [
                        'sidebar_label' => $doc->sidebar_label,
                        'slug' => $doc->slug,
                        'is_public' => (bool) $doc->is_public,
                        'status' => $doc->status,
                        'last_edited_by' => $doc->last_edited_by,
                        'file_order' => $doc->file_order,
                    ];
                });

            $sortedCategories = $subCategories
                ->filter(function ($cat) {
                    return $cat->position !== null;
                })
                ->sortBy('position')
                ->map(function ($cat) {
                    return [
                        'slug' => $cat->slug,
                        'sidebar_label' => $cat->sidebar_label,
                    ];
                });

            return response()->json([
                'documents' => $sortedDocuments->values(),
                'categories' => $sortedCategories->values(),
            ]);

        } catch (\Exception $e) {
            Log::error('ドキュメント一覧の取得に失敗しました: '.$e);

            return response()->json([
                'error' => 'ドキュメント一覧の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ドキュメントを作成
     */
    public function createDocument(CreateDocumentRequest $request): JsonResponse
    {
        // 認証チェック
        $user = $this->getUserFromSession();

        if (! $user) {
            return response()->json([
                'error' => '認証が必要です',
            ], 401);
        }

        // UseCaseを実行
        $result = $this->createDocumentUseCase->execute($request->all(), $user);

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 500);
        }

        return response()->json([
            'document' => $result['document'],
        ]);
    }

    /**
     * スラッグでドキュメントを取得
     */
    public function getDocumentDetail(GetDocumentDetailRequest $request): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            // パスから所属しているカテゴリのcategoryIdを取得
            $categoryPath = explode('/', $request->category_path);
            $categoryId = DocumentCategory::getIdFromPath($categoryPath);

            $document = DocumentVersion::where(function ($query) use ($categoryId, $request) {
                $query->where('category_id', $categoryId)
                    ->where('slug', $request->slug);
            })
                ->first();

            if (! $document) {
                return response()->json([
                    'error' => 'ドキュメントが見つかりません',
                ], 404);
            }

            return response()->json($document);

        } catch (\Exception $e) {
            Log::error('ドキュメント取得エラー: '.$e);

            return response()->json([
                'error' => 'ドキュメントの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ドキュメントを更新
     */
    public function updateDocument(UpdateDocumentRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 認証チェック
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }
            // 編集前のdocumentのIdからexistingDocumentを取得
            $existingDocument = DocumentVersion::find($request->current_document_id);

            if (! $existingDocument) {
                return response()->json([
                    'error' => '編集対象のドキュメントが見つかりません',
                ], 404);
            }

            // パスからslugとcategoryPathを取得（file_order処理用）
            $pathParts = explode('/', $request->category_path_with_slug);
            $slug = array_pop($pathParts);
            $categoryPath = $pathParts;
            $categoryId = DocumentCategory::getIdFromPath($categoryPath);

            // アクティブブランチを取得
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user, $request->edit_pull_request_id);

            // 編集セッションIDを取得
            $pullRequestEditSessionId = null;
            if ($request->edit_pull_request_id && $request->pull_request_edit_token) {
                $pullRequestEditSessionId = $this->pullRequestEditSessionService->getPullRequestEditSessionId(
                    $request->edit_pull_request_id,
                    $request->pull_request_edit_token,
                    $user->id
                );
            }

            // file_orderの処理
            $categoryId = $existingDocument->category_id;
            $finalFileOrder = $this->processFileOrder($request->file_order, $categoryId, $existingDocument->file_order, $userBranchId, $existingDocument->id);

            // 既存ドキュメントは論理削除せず、新しいドキュメントバージョンを作成
            $newDocumentVersion = DocumentVersion::create([
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId ?? null,
                'file_path' => $existingDocument->file_path,
                'status' => $pullRequestEditSessionId ? DocumentStatus::PUSHED->value : DocumentStatus::DRAFT->value,
                'content' => $request->content,
                'slug' => $request->slug,
                'sidebar_label' => $request->sidebar_label,
                'file_order' => $finalFileOrder,
                'last_edited_by' => $user->email,
                'is_public' => $request->is_public,
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

            DB::commit();

            return response()->json([
                'updated_document' => $newDocumentVersion,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ドキュメント更新エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'ドキュメントの更新に失敗しました',
            ], 500);
        }
    }

    /**
     * file_orderの処理と他のドキュメントの順序調整
     */
    private function processFileOrder($file_order, int $categoryId, int $old_file_order, int $userBranchId, int $excludeId): int
    {
        // file_orderが空の場合は最大値+1を設定
        if (empty($file_order) && $file_order !== 0) {
            $maxOrder = DocumentVersion::where('category_id', $categoryId)
                ->where('status', DocumentStatus::MERGED->value)
                ->max('file_order');

            return ($maxOrder ?? 0) + 1;
        }

        $new_file_order = (int) $file_order;

        // file_orderが変更された場合のみ他のドキュメントを調整
        if ($new_file_order !== $old_file_order) {
            $this->adjustOtherDocumentsOrder($categoryId, $new_file_order, $old_file_order, $userBranchId, $excludeId);
        }

        return $new_file_order;
    }

    /**
     * 他のドキュメントの順序を調整
     */
    private function adjustOtherDocumentsOrder(int $categoryId, int $new_file_order, int $old_file_order, int $userBranchId, int $excludeId): void
    {
        $documentsToShift = DocumentVersion::where('category_id', $categoryId)
            ->where(function ($query) use ($userBranchId) {
                $query->where('status', DocumentStatus::MERGED->value)
                    ->orWhere('user_branch_id', $userBranchId);
            })
            ->where('id', '!=', $excludeId);

        if ($new_file_order < $old_file_order) {
            // 上に移動する場合：新しい位置以上、元の位置未満の範囲のレコードを+1
            $documentsToShift = $documentsToShift
                ->where('file_order', '>=', $new_file_order)
                ->where('file_order', '<', $old_file_order)
                ->orderBy('file_order', 'asc');
        } else {
            // 下に移動する場合：元の位置超過、新しい位置以下の範囲のレコードを-1
            $documentsToShift = $documentsToShift
                ->where('file_order', '>', $old_file_order)
                ->where('file_order', '<=', $new_file_order)
                ->orderBy('file_order', 'asc');
        }

        $documents = $documentsToShift->get();

        foreach ($documents as $doc) {
            $newOrder = $new_file_order < $old_file_order ? $doc->file_order + 1 : $doc->file_order - 1;

            // 新しいバージョンを作成して順序を更新
            DocumentVersion::create([
                'user_id' => $doc->user_id,
                'user_branch_id' => $userBranchId,
                'file_path' => $doc->file_path,
                'status' => DocumentStatus::DRAFT->value,
                'content' => $doc->content,
                'slug' => $doc->slug,
                'sidebar_label' => $doc->sidebar_label,
                'file_order' => $newOrder,
                'last_edited_by' => $doc->last_edited_by,
                'is_deleted' => 0,
                'is_public' => $doc->is_public,
                'category_id' => $doc->category_id,
            ]);

            // 元のバージョンは論理削除しない
        }
    }

    /**
     * ドキュメントを削除
     */
    public function deleteDocument(DeleteDocumentRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. 認証チェック
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user, $request->edit_pull_request_id);

            $pullRequestEditSessionId = $this->pullRequestEditSessionService->getPullRequestEditSessionId(
                $request->edit_pull_request_id,
                $request->pull_request_edit_token,
                $user->id
            );

            $pathParts = array_filter(explode('/', $request->category_path_with_slug));
            $slug = array_pop($pathParts);
            $categoryPath = $pathParts;

            $categoryId = DocumentCategory::getIdFromPath($categoryPath);

            // 3. 削除対象のドキュメントを取得
            $existingDocument = DocumentVersion::where('category_id', $categoryId)
                ->where('slug', $slug)
                ->first();

            if (! $existingDocument) {
                return response()->json([
                    'error' => '削除対象のドキュメントが見つかりません',
                ], 404);
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
                        'target_type' => 'documents',
                        'current_version_id' => $existingDocument->id,
                    ],
                    [
                        'current_version_id' => $newDocumentVersion->id,
                        'diff_type' => 'deleted',
                    ]
                );
            }

            DB::commit();

            // 7. 成功レスポンス
            return response()->json();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ドキュメント削除エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'ドキュメントの削除に失敗しました',
            ], 500);
        }
    }

    /**
     * カテゴリコンテンツを取得
     */
    public function getCategoryContents(Request $request): JsonResponse
    {
        try {
            $slug = $request->query('slug');

            if (! $slug) {
                return response()->json([
                    'error' => '有効なslugが必要です',
                ], 400);
            }

            $category = DocumentCategory::where('slug', $slug)->first();
            if (! $category) {
                return response()->json([
                    'error' => 'カテゴリが見つかりません',
                ], 404);
            }

            // ドキュメントとサブカテゴリを取得
            $documents = DocumentVersion::where('category_id', $category->id)
                ->select('id', 'sidebar_label as name', 'slug', 'is_public')
                ->get()
                ->map(function ($doc) {
                    return [
                        'name' => $doc->name,
                        'path' => $doc->slug,
                        'type' => 'document',
                        'label' => $doc->name,
                        'isDraft' => ! $doc->is_public,
                    ];
                });

            $subCategories = DocumentCategory::where('parent_id', $category->id)
                ->select('id', 'name', 'slug')
                ->get()
                ->map(function ($cat) {
                    return [
                        'name' => $cat->name,
                        'path' => $cat->slug,
                        'type' => 'category',
                    ];
                });

            $items = $documents->concat($subCategories);

            return response()->json([
                'items' => $items,
            ]);

        } catch (\Exception $e) {
            Log::error('カテゴリコンテンツ取得エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'カテゴリコンテンツの取得に失敗しました',
            ], 500);
        }
    }
}

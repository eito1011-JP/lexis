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
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use App\Services\PullRequestEditSessionService;
use App\Services\UserBranchService;
use App\UseCases\Document\CreateDocumentUseCase;
use App\UseCases\Document\UpdateDocumentUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentController extends ApiBaseController
{
    protected DocumentService $documentService;

    protected UserBranchService $userBranchService;

    protected CreateDocumentUseCase $createDocumentUseCase;

    protected UpdateDocumentUseCase $updateDocumentUseCase;

    protected PullRequestEditSessionService $pullRequestEditSessionService;

    protected DocumentCategoryService $documentCategoryService;

    public function __construct(
        DocumentService $documentService,
        UserBranchService $userBranchService,
        CreateDocumentUseCase $createDocumentUseCase,
        UpdateDocumentUseCase $updateDocumentUseCase,
        PullRequestEditSessionService $pullRequestEditSessionService,
        DocumentCategoryService $documentCategoryService
    ) {
        $this->documentService = $documentService;
        $this->userBranchService = $userBranchService;
        $this->createDocumentUseCase = $createDocumentUseCase;
        $this->updateDocumentUseCase = $updateDocumentUseCase;
        $this->pullRequestEditSessionService = $pullRequestEditSessionService;
        $this->documentCategoryService = $documentCategoryService;
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
            $parentId = $this->documentCategoryService->getIdFromPath($categoryPath);

            $userBranchId = $user->userBranches()->active()->orderBy('id', 'desc')->first()->id ?? null;

            Log::info('userBranchId: '.$userBranchId);
            // edit_pull_request_idが存在する場合、プルリクエストからuser_branch_idを取得
            if ($request->edit_pull_request_id) {
                $pullRequest = PullRequest::find($request->edit_pull_request_id);
                $userBranchId = $pullRequest?->user_branch_id ?? null;
            }

            // サブカテゴリを取得
            $subCategories = $this->documentCategoryService->getSubCategories($parentId, $userBranchId, $request->edit_pull_request_id);

            // ドキュメントを取得
            $documents = $this->documentService->getDocumentsByCategoryId($parentId, $userBranchId, $request->edit_pull_request_id);

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
            $categoryId = $this->documentCategoryService->getIdFromPath($categoryPath);

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
        // 認証チェック
        $user = $this->getUserFromSession();

        if (! $user) {
            return response()->json([
                'error' => '認証が必要です',
            ], 401);
        }

        $result = $this->updateDocumentUseCase->execute($request->validated(), $user);

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 404);
        }

        return response()->json([
            'updated_document' => $result['document'],
        ]);
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

            $categoryId = $this->documentCategoryService->getIdFromPath($categoryPath);

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
                        'target_type' => EditStartVersionTargetType::DOCUMENT->value,
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

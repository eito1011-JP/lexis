<?php

namespace App\Http\Controllers\Api;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Http\Requests\Api\Document\CreateDocumentRequest;
use App\Http\Requests\Api\Document\DeleteDocumentRequest;
use App\Http\Requests\Api\Document\GetDocumentDetailRequest;
use App\Http\Requests\Api\Document\GetDocumentsRequest;
use App\Http\Requests\Api\Document\UpdateDocumentRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use App\Services\PullRequestEditSessionService;
use App\Services\UserBranchService;
use App\UseCases\Document\CreateDocumentUseCase;
use App\UseCases\Document\DeleteDocumentUseCase;
use App\UseCases\Document\GetDocumentDetailUseCase;
use App\UseCases\Document\GetDocumentsUseCase;
use App\UseCases\Document\UpdateDocumentUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentController extends ApiBaseController
{
    protected DocumentService $documentService;

    protected UserBranchService $userBranchService;

    protected CreateDocumentUseCase $createDocumentUseCase;

    protected GetDocumentsUseCase $getDocumentsUseCase;

    protected UpdateDocumentUseCase $updateDocumentUseCase;

    protected GetDocumentDetailUseCase $getDocumentDetailUseCase;

    protected DeleteDocumentUseCase $deleteDocumentUseCase;

    protected PullRequestEditSessionService $pullRequestEditSessionService;

    protected DocumentCategoryService $documentCategoryService;

    public function __construct(
        DocumentService $documentService,
        UserBranchService $userBranchService,
        CreateDocumentUseCase $createDocumentUseCase,
        GetDocumentsUseCase $getDocumentsUseCase,
        UpdateDocumentUseCase $updateDocumentUseCase,
        GetDocumentDetailUseCase $getDocumentDetailUseCase,
        DeleteDocumentUseCase $deleteDocumentUseCase,
        PullRequestEditSessionService $pullRequestEditSessionService,
        DocumentCategoryService $documentCategoryService
    ) {
        $this->documentService = $documentService;
        $this->userBranchService = $userBranchService;
        $this->createDocumentUseCase = $createDocumentUseCase;
        $this->getDocumentsUseCase = $getDocumentsUseCase;
        $this->updateDocumentUseCase = $updateDocumentUseCase;
        $this->getDocumentDetailUseCase = $getDocumentDetailUseCase;
        $this->deleteDocumentUseCase = $deleteDocumentUseCase;
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
        // 認証チェック（新しいメソッドを使用）
        $user = $this->getUserFromSession();

        if (! $user) {
            return response()->json([
                'error' => '認証が必要です',
            ], 401);
        }

        // UseCaseを実行
        $result = $this->getDocumentsUseCase->execute($request->validated(), $user);

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 500);
        }

        return response()->json([
            'documents' => $result['documents'],
            'categories' => $result['categories'],
        ]);
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

            // UseCaseを使用してドキュメントを取得
            $result = $this->getDocumentDetailUseCase->execute(
                $request->category_path,
                $request->slug
            );

            if (! $result['success']) {
                return response()->json([
                    'error' => $result['error'],
                ], 404);
            }

            return response()->json($result['document']);

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

        $dto = new UpdateDocumentDto($request);
        $this->updateDocumentUseCase->execute($dto, $user);

        return response()->json();
    }

    /**
     * ドキュメントを削除
     */
    public function deleteDocument(DeleteDocumentRequest $request): JsonResponse
    {
        // 認証チェック
        $user = $this->getUserFromSession();

        if (! $user) {
            return response()->json([
                'error' => '認証が必要です',
            ], 401);
        }

        // UseCaseを実行
        $result = $this->deleteDocumentUseCase->execute([
            'category_path_with_slug' => $request->category_path_with_slug,
            'edit_pull_request_id' => $request->edit_pull_request_id,
            'pull_request_edit_token' => $request->pull_request_edit_token,
        ], $user);

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 404);
        }

        return response()->json();
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

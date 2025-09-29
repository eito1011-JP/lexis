<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\Document\CreateDocumentUseCaseDto;
use App\Dto\UseCase\Document\GetDocumentsDto;
use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Dto\UseCase\DocumentVersion\DetailDto;
use App\Http\Requests\Api\Document\CreateDocumentRequest;
use App\Http\Requests\Api\Document\DeleteDocumentRequest;
use App\Http\Requests\Api\Document\DetailRequest;
use App\Http\Requests\Api\Document\GetDocumentsRequest;
use App\Http\Requests\Api\Document\UpdateDocumentRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use App\UseCases\Document\CreateDocumentUseCase;
use App\UseCases\Document\DeleteDocumentUseCase;
use App\UseCases\Document\DetailUseCase;
use App\UseCases\Document\GetDocumentsUseCase;
use App\UseCases\Document\UpdateDocumentUseCase;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class DocumentController extends ApiBaseController
{
    protected DocumentService $documentService;

    protected UserBranchService $userBranchService;

    protected CreateDocumentUseCase $createDocumentUseCase;

    protected GetDocumentsUseCase $getDocumentsUseCase;

    protected UpdateDocumentUseCase $updateDocumentUseCase;

    protected DetailUseCase $getDocumentDetailUseCase;

    protected DeleteDocumentUseCase $deleteDocumentUseCase;

    protected DocumentCategoryService $documentCategoryService;

    public function __construct(
        DocumentService $documentService,
        UserBranchService $userBranchService,
        CreateDocumentUseCase $createDocumentUseCase,
        GetDocumentsUseCase $getDocumentsUseCase,
        UpdateDocumentUseCase $updateDocumentUseCase,
        DetailUseCase $getDocumentDetailUseCase,
        DeleteDocumentUseCase $deleteDocumentUseCase,
        DocumentCategoryService $documentCategoryService
    ) {
        $this->documentService = $documentService;
        $this->userBranchService = $userBranchService;
        $this->createDocumentUseCase = $createDocumentUseCase;
        $this->getDocumentsUseCase = $getDocumentsUseCase;
        $this->updateDocumentUseCase = $updateDocumentUseCase;
        $this->getDocumentDetailUseCase = $getDocumentDetailUseCase;
        $this->deleteDocumentUseCase = $deleteDocumentUseCase;

        $this->documentCategoryService = $documentCategoryService;
    }

    /**
     * ドキュメント一覧を取得
     */
    public function getDocuments(GetDocumentsRequest $request): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return response()->json([
                'error' => '認証が必要です',
            ], 401);
        }

        // DTOを作成してUseCaseを実行
        $dto = GetDocumentsDto::fromArray($request->validated());
        // $result = $this->getDocumentsUseCase->execute($dto, $user);

        return response()->json([
            'documents' => [],
            'categories' => [],
        ]);
        // if (! $result['success']) {
        //     return response()->json([
        //         'error' => $result['error'],
        //     ], 500);
        // }

        // return response()->json([
        //     'documents' => $result['documents'],
        //     'categories' => $result['categories'],
        // ]);
    }

    /**
     * ドキュメントを作成
     */
    public function createDocument(CreateDocumentRequest $request, CreateDocumentUseCase $useCase): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // DTOを作成してUseCaseを実行
            $dto = CreateDocumentUseCaseDto::fromRequest($request->all(), $user);
            $useCase->execute($dto);

            return response()->json();
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * ドキュメントバージョンIDでドキュメントを取得
     */
    public function detail(DetailRequest $request, DetailUseCase $useCase): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // DTOを作成してUseCaseを実行
            $validatedData = $request->validated();
            $dto = DetailDto::fromRequest($validatedData);
            $result = $useCase->execute($dto, $user);

            return response()->json($result);
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * ドキュメントを更新
     */
    public function updateDocument(UpdateDocumentRequest $request): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            $validatedRequest = $request->validated();
            $updateDocumentDto = new UpdateDocumentDto(
                document_entity_id: $validatedRequest['document_entity_id'],
                title: $validatedRequest['title'],
                description: $validatedRequest['description'],
                edit_pull_request_id: $validatedRequest['edit_pull_request_id'] ?? null,
                pull_request_edit_token: $validatedRequest['pull_request_edit_token'] ?? null,
            );
            $this->updateDocumentUseCase->execute($updateDocumentDto, $user);

            return response()->json();
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * ドキュメントを削除
     */
    public function deleteDocument(DeleteDocumentRequest $request): JsonResponse
    {
        // 認証チェック
        $user = $this->user();

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

            $subCategories = DocumentCategory::where('parent_entity_id', $category->id)
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

<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\Document\GetDocumentDetailDto;
use App\Dto\UseCase\Document\GetDocumentsDto;
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
use App\Services\UserBranchService;
use App\Dto\UseCase\Document\CreateDocumentUseCaseDto;
use App\Exceptions\BaseException;
use App\UseCases\Document\CreateDocumentUseCase;
use Http\Discovery\Exception\NotFoundException;
use App\UseCases\Document\DeleteDocumentUseCase;
use App\UseCases\Document\GetDocumentDetailUseCase;
use App\UseCases\Document\GetDocumentsUseCase;
use App\UseCases\Document\UpdateDocumentUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Psr\Log\LogLevel;

class DocumentController extends ApiBaseController
{
    protected DocumentService $documentService;

    protected UserBranchService $userBranchService;

    protected CreateDocumentUseCase $createDocumentUseCase;

    protected GetDocumentsUseCase $getDocumentsUseCase;

    protected UpdateDocumentUseCase $updateDocumentUseCase;

    protected GetDocumentDetailUseCase $getDocumentDetailUseCase;

    protected DeleteDocumentUseCase $deleteDocumentUseCase;

    protected DocumentCategoryService $documentCategoryService;

    public function __construct(
        DocumentService $documentService,
        UserBranchService $userBranchService,
        CreateDocumentUseCase $createDocumentUseCase,
        GetDocumentsUseCase $getDocumentsUseCase,
        UpdateDocumentUseCase $updateDocumentUseCase,
        GetDocumentDetailUseCase $getDocumentDetailUseCase,
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
        } catch (NotFoundException) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                __('errors.MSG_NOT_FOUND'),
                ErrorType::STATUS_NOT_FOUND,
                LogLevel::ERROR,
            );
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
     * スラッグでドキュメントを取得
     */
    public function getDocumentDetail(GetDocumentDetailRequest $request): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            // DTOを作成してUseCaseを実行
            $dto = GetDocumentDetailDto::fromArray($request->validated());
            $result = $this->getDocumentDetailUseCase->execute($dto);

            return response()->json($result);
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
        $user = $this->user();

        if (! $user) {
            return response()->json([
                'error' => '認証が必要です',
            ], 401);
        }

        $validatedRequest = $request->validated();
        $payload = [
            'category_path' => $validatedRequest['category_path'] ?? null,
            'current_document_id' => $validatedRequest['current_document_id'],
            'sidebar_label' => $validatedRequest['sidebar_label'],
            'content' => $validatedRequest['content'],
            'is_public' => $validatedRequest['is_public'],
            'slug' => $validatedRequest['slug'],
            'file_order' => $validatedRequest['file_order'] ?? null,
            'edit_pull_request_id' => $validatedRequest['edit_pull_request_id'] ?? null,
            'pull_request_edit_token' => $validatedRequest['pull_request_edit_token'] ?? null,
        ];
        $updateDocumentDto = UpdateDocumentDto::fromArray($payload);
        $this->updateDocumentUseCase->execute($updateDocumentDto, $user);

        return response()->json();
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

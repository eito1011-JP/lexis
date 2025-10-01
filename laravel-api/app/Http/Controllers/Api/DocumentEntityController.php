<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\Document\CreateDocumentUseCaseDto;
use App\Dto\UseCase\Document\DestroyDocumentDto;
use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Dto\UseCase\DocumentVersion\DetailDto;
use App\Http\Requests\Api\Document\CreateDocumentRequest;
use App\Http\Requests\Api\Document\DetailRequest;
use App\Http\Requests\Api\Document\DestroyDocumentRequest;
use App\Http\Requests\Api\Document\UpdateDocumentRequest;
use App\Services\CategoryService;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use App\UseCases\Document\CreateDocumentUseCase;
use App\UseCases\Document\DestroyDocumentUseCase;
use App\UseCases\Document\DetailUseCase;
use App\UseCases\Document\UpdateDocumentUseCase;
use Exception;
use Illuminate\Http\JsonResponse;
use Psr\Log\LogLevel;

class DocumentEntityController extends ApiBaseController
{
    protected DocumentService $documentService;

    protected UserBranchService $userBranchService;

    protected CreateDocumentUseCase $createDocumentUseCase;

    protected UpdateDocumentUseCase $updateDocumentUseCase;

    protected DetailUseCase $getDocumentDetailUseCase;

    protected DestroyDocumentUseCase $destroyDocumentUseCase;

    protected CategoryService $CategoryService;

    public function __construct(
        DocumentService $documentService,
        UserBranchService $userBranchService,
        CreateDocumentUseCase $createDocumentUseCase,
        UpdateDocumentUseCase $updateDocumentUseCase,
        DetailUseCase $getDocumentDetailUseCase,
        DestroyDocumentUseCase $destroyDocumentUseCase,
        CategoryService $CategoryService
    ) {
        $this->documentService = $documentService;
        $this->userBranchService = $userBranchService;
        $this->createDocumentUseCase = $createDocumentUseCase;
        $this->updateDocumentUseCase = $updateDocumentUseCase;
        $this->getDocumentDetailUseCase = $getDocumentDetailUseCase;
        $this->destroyDocumentUseCase = $destroyDocumentUseCase;

        $this->CategoryService = $CategoryService;
    }

    /**
     * ドキュメントを作成
     */
    public function store(CreateDocumentRequest $request, CreateDocumentUseCase $useCase): JsonResponse
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
    public function show(DetailRequest $request, DetailUseCase $useCase): JsonResponse
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
    public function update(UpdateDocumentRequest $request): JsonResponse
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
    public function destroy(DestroyDocumentRequest $request, DestroyDocumentUseCase $useCase): JsonResponse
    {
        try {
        $user = $this->user();

        if (! $user) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        }

        $validatedData = $request->validated();
        $dto = new DestroyDocumentDto(
            document_entity_id: $validatedData['document_entity_id'],
            edit_pull_request_id: $validatedData['edit_pull_request_id'] ?? null,
            pull_request_edit_token: $validatedData['pull_request_edit_token'] ?? null,
        );
        $useCase->execute($dto, $user);

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
}

<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\DocumentCategory\CreateDocumentCategoryDto;
use App\Dto\UseCase\DocumentCategory\DestroyCategoryEntityDto;
use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Dto\UseCase\DocumentCategory\GetCategoryDto;
use App\Dto\UseCase\DocumentCategory\UpdateDocumentCategoryDto;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Http\Requests\Api\DocumentCategory\FetchCategoriesRequest;
use App\Http\Requests\Api\DocumentCategory\GetCategoryRequest;
use App\Http\Requests\Api\DocumentCategory\UpdateDocumentCategoryRequest;
use App\Http\Requests\CreateDocumentCategoryRequest;
use App\Http\Requests\DeleteDocumentCategoryRequest;
use App\Services\CategoryService;
use App\Services\UserBranchService;
use App\UseCases\DocumentCategory\CreateDocumentCategoryUseCase;
use App\UseCases\DocumentCategory\DestroyDocumentCategoryUseCase;
use App\UseCases\DocumentCategory\FetchCategoriesUseCase;
use App\UseCases\DocumentCategory\GetCategoryUseCase;
use App\UseCases\DocumentCategory\UpdateDocumentCategoryUseCase;
use Exception;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class CategoryEntityController extends ApiBaseController
{
    protected $CategoryService;

    protected $userBranchService;

    public function __construct(
        CategoryService $CategoryService,
        UserBranchService $userBranchService
    ) {
        $this->CategoryService = $CategoryService;
        $this->userBranchService = $userBranchService;
    }

    /**
     * カテゴリ一覧を取得
     */
    public function index(FetchCategoriesRequest $request, FetchCategoriesUseCase $useCase): JsonResponse
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
            $dto = FetchCategoriesDto::fromRequest($request->validated());
            $categories = $useCase->execute($dto, $user);

            return response()->json([
                'categories' => $categories,
            ]);

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
     * カテゴリ詳細を取得
     */
    public function show(GetCategoryRequest $request, GetCategoryUseCase $useCase): JsonResponse
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
            $data = $request->validated();
            $data['user'] = $user;

            $dto = GetCategoryDto::fromRequest($data);
            $category = $useCase->execute($dto);

            return response()->json([
                'category' => $category,
            ]);

        } catch (AuthenticationException) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        } catch (NotFoundException) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                __('errors.MSG_NOT_FOUND'),
                ErrorType::STATUS_NOT_FOUND,
            );
        } catch (Exception $e) {
            Log::error('カテゴリ詳細取得に失敗しました', [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * カテゴリを作成
     */
    public function store(CreateDocumentCategoryRequest $request, CreateDocumentCategoryUseCase $useCase): JsonResponse
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

            // DTOを作成
            $dto = CreateDocumentCategoryDto::fromRequest($request->validated());

            // UseCaseを実行
            $useCase->execute($dto, $user);

            return response()->json();
        } catch (AuthenticationException) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        } catch (NotFoundException) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                __('errors.MSG_NOT_FOUND'),
                ErrorType::STATUS_NOT_FOUND,
            );
        } catch (DuplicateExecutionException) {
            return $this->sendError(
                ErrorType::CODE_DUPLICATE_EXECUTION,
                __('errors.MSG_DUPLICATE_EXECUTION'),
                ErrorType::STATUS_DUPLICATE_EXECUTION,
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
     * カテゴリを更新
     */
    public function update(UpdateDocumentCategoryRequest $request, UpdateDocumentCategoryUseCase $useCase): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            // 3. 認証ユーザーではない時は401エラー
            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // 2. 認証ユーザーの時
            // DTOを作成
            $validatedData = $request->validated();
            $dto = UpdateDocumentCategoryDto::fromRequest($validatedData);

            // UseCaseを実行
            $useCase->execute($dto, $user);

            return response()->json();

        } catch (AuthenticationException) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        } catch (NotFoundException) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                __('errors.MSG_NOT_FOUND'),
                ErrorType::STATUS_NOT_FOUND,
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
     * カテゴリを削除
     */
    public function destroy(DeleteDocumentCategoryRequest $request, DestroyDocumentCategoryUseCase $useCase): JsonResponse
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

            // DTOを作成
            $validatedData = $request->validated();
            $dto = DestroyCategoryEntityDto::fromRequest($validatedData);

            // UseCaseを実行
            $useCase->execute($dto, $user);

            return response()->json();

        } catch (AuthenticationException) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        } catch (NotFoundException) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                __('errors.MSG_NOT_FOUND'),
                ErrorType::STATUS_NOT_FOUND,
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
}

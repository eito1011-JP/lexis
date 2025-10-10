<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\UserBranchSession\DestroyDto;
use App\Dto\UseCase\UserBranchSession\StoreDto;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Exceptions\NotAuthorizedException;
use App\Exceptions\TargetDocumentNotFoundException;
use App\Http\Requests\Api\UserBranchSession\DestroyRequest;
use App\Http\Requests\Api\UserBranchSession\StoreRequest;
use App\UseCases\UserBranchSession\DestroyUseCase;
use App\UseCases\UserBranchSession\StoreUseCase;
use Exception;
use App\Exceptions\NotFoundException;
use Illuminate\Http\JsonResponse;
use Psr\Log\LogLevel;

class UserBranchSessionController extends ApiBaseController
{
    protected DestroyUseCase $destroyUserBranchSessionUseCase;
    protected StoreUseCase $storeUserBranchSessionUseCase;

    public function __construct(
        DestroyUseCase $destroyUserBranchSessionUseCase,
        StoreUseCase $storeUserBranchSessionUseCase
    ) {
        $this->destroyUserBranchSessionUseCase = $destroyUserBranchSessionUseCase;
        $this->storeUserBranchSessionUseCase = $storeUserBranchSessionUseCase;
    }

    /**
     * ユーザーブランチセッションを作成
     */
    public function store(StoreRequest $request): JsonResponse
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
            $dto = new StoreDto(
                pullRequestId: $validatedData['pull_request_id'],
                user: $user,
            );

            $this->storeUserBranchSessionUseCase->execute($dto);

            return response()->json();

        } catch (AuthenticationException $e) {
            return $e->toResponse($request);
        } catch (NotFoundException $e) {
            return $e->toResponse($request);
        } catch (NotAuthorizedException $e) {
            return $e->toResponse($request);
        } catch (DuplicateExecutionException $e) {
            return $e->toResponse($request);
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
     * ユーザーブランチセッションを削除
     */
    public function destroy(DestroyRequest $request): JsonResponse
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
            $dto = new DestroyDto(
                userBranchId: $validatedData['user_branch_id'],
                user: $user,
            );

            $this->destroyUserBranchSessionUseCase->execute($dto);

            return response()->json();

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

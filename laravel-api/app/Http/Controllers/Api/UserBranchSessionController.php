<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\UserBranchSession\DestroyDto;
use App\Http\Requests\Api\UserBranchSession\DestroyRequest;
use App\UseCases\UserBranchSession\DestroyUseCase;
use Exception;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Http\JsonResponse;
use Psr\Log\LogLevel;

class UserBranchSessionController extends ApiBaseController
{
    protected DestroyUseCase $destroyUserBranchSessionUseCase;

    public function __construct(DestroyUseCase $destroyUserBranchSessionUseCase)
    {
        $this->destroyUserBranchSessionUseCase = $destroyUserBranchSessionUseCase;
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

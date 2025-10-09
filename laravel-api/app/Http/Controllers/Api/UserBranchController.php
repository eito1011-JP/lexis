<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\UserBranch\DestroyUserBranchDto;
use App\Http\Requests\Api\UserBranch\DestroyUserBranchRequest;
use App\Http\Requests\Api\UserBranch\FetchDiffRequest;
use App\Services\DocumentDiffService;
use App\Services\UserBranchService;
use App\UseCases\UserBranch\DestroyUserBranchUseCase;
use App\UseCases\UserBranch\FetchDiffUseCase;
use Exception;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class UserBranchController extends ApiBaseController
{
    protected DocumentDiffService $documentDiffService;

    protected FetchDiffUseCase $fetchDiffUseCase;

    protected DestroyUserBranchUseCase $destroyUserBranchUseCase;

    protected UserBranchService $userBranchService;

    public function __construct(
        DocumentDiffService $documentDiffService,
        FetchDiffUseCase $fetchDiffUseCase,
        DestroyUserBranchUseCase $destroyUserBranchUseCase,
        UserBranchService $userBranchService
    ) {
        $this->documentDiffService = $documentDiffService;
        $this->fetchDiffUseCase = $fetchDiffUseCase;
        $this->destroyUserBranchUseCase = $destroyUserBranchUseCase;
        $this->userBranchService = $userBranchService;
    }

    /**
     * Git差分チェック
     */
    public function hasUserChanges(): JsonResponse
    {
        try {
            // Cookieセッションからユーザー情報を取得
            $loginUser = $this->user();

            if (! $loginUser) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // アクティブなユーザーブランチセッションを取得
            $activeSession = $this->userBranchService->hasUserActiveBranchSession($loginUser, $loginUser->organizationMember->organization_id);

            return response()->json([
                'has_user_changes' => $activeSession ? true : false,
                'user_branch_id' => $activeSession ? $activeSession->id : null,
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
     * Git差分取得
     */
    public function fetchDiff(FetchDiffRequest $request): JsonResponse
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

            // UseCaseを実行
            $diffResult = $this->fetchDiffUseCase->execute($user, $request->validated()['user_branch_id']);

            return response()->json($diffResult);

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
     * ユーザーブランチを削除
     */
    public function destroy(DestroyUserBranchRequest $request): JsonResponse
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

            // UseCaseを実行
            $dto = new DestroyUserBranchDto(
                $request->validated()['user_branch_id'],
                $user
            );
            $this->destroyUserBranchUseCase->execute($dto);

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

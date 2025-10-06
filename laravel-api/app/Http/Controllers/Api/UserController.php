<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Models\DocumentVersion;
use App\UseCases\User\UserMeUseCase;
use Exception;
use Illuminate\Http\JsonResponse;

class UserController extends ApiBaseController
{
    public function __construct(
        private UserMeUseCase $userMeUseCase
    ) {}

    /**
     * ユーザー一覧を取得（検索対応）
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'users' => DocumentVersion::all(),
        ]);
    }

    /**
     * 現在のログインユーザー情報を取得
     */
    public function me(): JsonResponse
    {
        try {
            $user = $this->user();

            if (!$user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            $result = $this->userMeUseCase->execute($user);

            return response()->json([
                'user' => $result['user'],
                'organization' => $result['organization'],
                'activeUserBranch' => $result['activeUserBranch'],
            ]);
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
            );
        }
    }
}

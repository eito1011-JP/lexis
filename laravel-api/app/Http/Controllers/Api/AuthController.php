<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\UseCases\Auth\LogoutUseCase;
use Exception;
use Illuminate\Http\JsonResponse;
use Psr\Log\LogLevel;

class AuthController extends ApiBaseController
{
    public function __construct(
        private LogoutUseCase $logoutUseCase
    ) {}

    /**
     * ログアウト（現在のアクセストークンを無効化）
     */
    public function logout(): JsonResponse
    {
        try {
            $result = $this->logoutUseCase->execute();

            return response()
                ->json()
                ->withCookie($result['cookie']);
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    public function me(): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'user' => $user,
        ]);
    }
}

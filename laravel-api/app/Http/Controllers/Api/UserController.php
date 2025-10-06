<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Models\DocumentVersion;
use Exception;
use Illuminate\Http\JsonResponse;

class UserController extends ApiBaseController
{
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

        return response()->json([
                'user' => $user,
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

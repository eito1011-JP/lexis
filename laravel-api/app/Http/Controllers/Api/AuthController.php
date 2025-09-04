<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

class AuthController extends ApiBaseController
{
    /**
     * ログアウト（現在のアクセストークンを無効化）
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // クッキーを無効化
            Cookie::queue(Cookie::forget('sid'));

            return response()->json([
                'success' => true,
                'message' => 'ログアウトしました',
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'error' => 'サーバーエラーが発生しました',
            ], 500);
        }
    }

    public function me(): JsonResponse
    {
        return response()->json($this->user());
    }
}

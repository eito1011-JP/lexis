<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\SignupRequest;
use App\Models\User;
use App\UseCases\Auth\SignupUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends ApiBaseController
{
    /**
     * ログアウト（現在のアクセストークンを無効化）
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->user()?->currentAccessToken();
            if ($token) {
                $token->delete();
            }

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
}

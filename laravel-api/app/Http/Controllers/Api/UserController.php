<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiBaseController
{
    /**
     * ユーザー一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $users = User::select('id', 'email', 'created_at')->get();

            return response()->json([
                'users' => $users,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ユーザー一覧の取得に失敗しました',
            ], 500);
        }
    }
}

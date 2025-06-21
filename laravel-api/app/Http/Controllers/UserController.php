<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * ユーザー一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $users = User::select('id', 'email', 'created_at')->get();

            return response()->json([
                'users' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ユーザー一覧の取得に失敗しました'
            ], 500);
        }
    }
} 
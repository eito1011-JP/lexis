<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\FetchUsersRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends ApiBaseController
{
    /**
     * ユーザー一覧を取得（検索対応）
     */
    public function index(FetchUsersRequest $request): JsonResponse
    {
        try {
            // 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // バリデーション済みの検索クエリを取得
            $searchEmail = $request->validated('email');

            // 削除されていない全ユーザーを取得
            $query = User::whereNull('deleted_at');

            // email検索が指定されている場合
            if (! empty($searchEmail)) {
                $query->where('email', 'like', '%'.$searchEmail.'%');
            }

            $users = $query->select('id', 'email', 'role', 'created_at')->get();

            return response()->json([
                'users' => $users,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ユーザー一覧の取得に失敗しました',
            ], 500);
        }
    }
}

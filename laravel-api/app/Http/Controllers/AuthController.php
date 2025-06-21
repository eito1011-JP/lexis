<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * ユーザー登録
     */
    public function signup(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8',
            ], [
                'email.required' => 'メールアドレスは必須です',
                'email.email' => '有効なメールアドレスを入力してください',
                'email.unique' => 'このメールアドレスは既に登録されています',
                'password.required' => 'パスワードは必須です',
                'password.min' => 'パスワードは8文字以上である必要があります',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 400);
            }

            // ユーザー作成
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // セッション作成
            $sessionId = $this->createSession($user->id, $user->email);

            // クッキーにセッションIDを設定
            $cookie = cookie('sid', $sessionId, 90 * 24 * 60); // 90日

            return response()->json([
                'success' => true,
                'message' => 'ユーザー登録が完了しました',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'createdAt' => $user->created_at,
                ],
                'isAuthenticated' => true,
            ])->withCookie($cookie);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * ログイン
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ], [
                'email.required' => 'メールアドレスは必須です',
                'password.required' => 'パスワードは必須です',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 400);
            }

            // ユーザーの存在確認
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'error' => 'メールアドレスまたはパスワードが正しくありません'
                ], 401);
            }

            // パスワードの検証
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'error' => 'メールアドレスまたはパスワードが正しくありません'
                ], 401);
            }

            // セッション作成
            $sessionId = $this->createSession($user->id, $user->email);

            // クッキーにセッションIDを設定
            $cookie = cookie('sid', $sessionId, 90 * 24 * 60); // 90日

            return response()->json([
                'success' => true,
                'message' => 'ログインに成功しました',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'createdAt' => $user->created_at,
                ],
                'isAuthenticated' => true,
            ])->withCookie($cookie);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * セッション確認
     */
    public function session(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->cookie('sid');

            if (!$sessionId) {
                return response()->json([
                    'authenticated' => false,
                    'message' => 'セッションがありません',
                ]);
            }

            $user = $this->getSessionUser($sessionId);

            if (!$user) {
                return response()->json([
                    'authenticated' => false,
                    'message' => 'セッションが無効または期限切れです',
                ]);
            }

            return response()->json([
                'authenticated' => true,
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'authenticated' => false,
                'error' => 'セッション確認中にエラーが発生しました',
            ], 500);
        }
    }

    /**
     * ログアウト
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->cookie('sid');

            if ($sessionId) {
                $this->deleteSession($sessionId);
            }

            // クッキーを削除
            $cookie = cookie('sid', '', -1);

            return response()->json([
                'success' => true,
                'message' => 'ログアウトしました',
            ])->withCookie($cookie);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * セッション作成
     */
    private function createSession(int $userId, string $email): string
    {
        $expireAt = now()->addDays(90); // 90日後に期限切れ

        $sessionData = [
            'userId' => $userId,
            'email' => $email,
            'createdAt' => now()->toISOString(),
        ];

        // 既存のセッションを確認
        $existingSession = Session::where('user_id', $userId)->first();

        if ($existingSession) {
            // 既存のセッションがある場合は更新
            $existingSession->update([
                'sess' => json_encode($sessionData),
                'expired_at' => $expireAt,
            ]);
            return (string) $existingSession->id;
        } else {
            // 新規セッションの作成
            $session = Session::create([
                'user_id' => $userId,
                'sess' => json_encode($sessionData),
                'expired_at' => $expireAt,
            ]);
            return (string) $session->id;
        }
    }

    /**
     * セッションからユーザー情報を取得
     */
    private function getSessionUser(string $sessionId): ?array
    {
        $session = Session::where('id', $sessionId)
            ->where('expired_at', '>', now())
            ->first();

        if (!$session) {
            return null;
        }

        $sessionData = json_decode($session->sess, true);
        return [
            'userId' => $sessionData['userId'],
            'email' => $sessionData['email'],
        ];
    }

    /**
     * セッション削除
     */
    private function deleteSession(string $sessionId): void
    {
        Session::where('id', $sessionId)->delete();
    }
} 
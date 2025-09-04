<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\SignupRequest;
use App\Models\Session;
use App\Models\User;
use App\UseCases\Auth\SignupUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends ApiBaseController
{
    public function __construct(
        private SignupUseCase $signupUseCase
    ) {}

    /**
     * ユーザー登録
     */
    public function signup(SignupRequest $request): JsonResponse
    {
        $result = $this->signupUseCase->execute($request->email, $request->password);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
            ], 500);
        }

        // クッキーにセッションIDを設定
        $cookie = cookie('sid', $result['sessionId'], config('session.lifetime'));

        return response()->json([
            'user' => $result['user'],
            'isAuthenticated' => true,
        ])->withCookie($cookie);
    }

    /**
     * セッション確認
     */
    public function session(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->cookie('sid');

            if (! $sessionId) {
                return response()->json([
                    'authenticated' => false,
                    'message' => 'セッションがありません',
                ]);
            }

            $user = $this->getSessionUser($sessionId);

            if (! $user) {
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
                'error' => 'サーバーエラーが発生しました',
            ], 500);
        }
    }

    /**
     * セッション作成
     */
    private function createSession(int $userId, string $email): string
    {
        $expireAt = now()->addMinutes(config('session.lifetime')); // 設定ファイルから取得

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

        Log::info($session);
        if (! $session) {
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

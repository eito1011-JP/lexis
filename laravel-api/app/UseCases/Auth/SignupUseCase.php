<?php

namespace App\UseCases\Auth;

use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SignupUseCase
{
    /**
     * ユーザー登録を実行
     *
     * @param  string  $email  メールアドレス
     * @param  string  $password  パスワード
     * @return array{success: bool, user?: array, sessionId?: string, error?: string}
     */
    public function execute(string $email, string $password): array
    {
        try {
            // ユーザー作成
            $user = User::create([
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            // セッション作成
            $sessionId = $this->createSession($user->id, $user->email);

            return [
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'createdAt' => $user->created_at,
                ],
                'sessionId' => $sessionId,
            ];

        } catch (\Exception $e) {
            Log::error('SignupUseCase: エラー', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);

            return [
                'success' => false,
                'error' => 'サーバーエラーが発生しました',
            ];
        }
    }

    /**
     * セッション作成
     */
    private function createSession(int $userId, string $email): string
    {
        $expireAt = now()->addMinutes(config('session.lifetime'));

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
}

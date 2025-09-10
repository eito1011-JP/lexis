<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Consts\ErrorType;
use App\Consts\Flag;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NoAccountException;
use App\Exceptions\TooManyRequestsException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SigninWithEmailUseCase
{
    public function __construct() {}

    /**
     * メールアドレスとパスワードでサインインを実行
     *
     * @param string $email メールアドレス
     * @param string $password パスワード
     * @param Request $request リクエストオブジェクト（ログイン試行回数管理用）
     * @return array レスポンスデータとクッキー情報
     * @throws TooManyRequestsException ログイン試行回数超過時
     * @throws NoAccountException アカウントが存在しない場合
     * @throws AuthenticationException 認証失敗時
     */
    public function execute(string $email, string $password, Request $request): array
    {
        try {
        $key = 'signin-with-email.' . $request->ip() . '.' . $email;

        // ログイン試行回数チェック
        if (RateLimiter::tooManyAttempts($key, config('auth.login_attempts.max_attempts'))) {
            throw new TooManyRequestsException();
        }

        // ユーザー存在チェック
        $user = User::byEmail($email)->first();
        if (!$user) {
            throw new NoAccountException();
        }

        // パスワード検証
        if (!Hash::check($password, $user->password)) {
            RateLimiter::hit($key, config('auth.login_attempts.lockout_decay_minutes') * 60);
            if (RateLimiter::tooManyAttempts($key, config('auth.login_attempts.max_attempts'))) {
                throw new TooManyRequestsException();
            }

            throw new AuthenticationException(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        }

        // ログイン試行回数をクリア
        RateLimiter::clear($key);

        // 最終ログイン日時を更新
        $user->update(['last_login' => now()]);

        // セッショントークン（クッキー保管用）を生成
        $ttlMinutes = config('session.lifetime');
        $expiresAt = now()->addMinutes($ttlMinutes);

        $payload = json_encode([
            'uid' => $user->id,
            'email' => $user->email,
            'exp' => $expiresAt->timestamp,
            'jti' => (string) Str::uuid(),
        ], JSON_UNESCAPED_UNICODE);

        $token = Crypt::encryptString($payload);

        // クッキー設定情報（ローカル開発環境ではドメインをnullに設定）
        $cookie = cookie(
            name: 'sid',
            value: $token,
            minutes: $ttlMinutes,
            path: config('session.path', '/'),
            domain: null, // ローカル開発環境でクロスドメインクッキーが正しく動作するようnullを設定
            secure: false, // HTTPでも動作するようfalseに設定
            httpOnly: (bool) config('session.http_only', true),
            raw: false,
            sameSite: config('session.same_site', 'lax')
        );

        return [
            'user' => $user,
            'cookie' => $cookie,
            ];
        } catch (\Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}

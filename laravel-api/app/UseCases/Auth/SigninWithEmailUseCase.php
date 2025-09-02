<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Consts\ErrorType;
use App\Consts\Flag;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NoAccountException;
use App\Exceptions\TooManyRequestsException;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class SigninWithEmailUseCase
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DECAY_MINUTES = 15;

    public function __construct(
        private JwtService $jwtService
    ) {}

    /**
     * メールアドレスとパスワードでサインインを実行
     *
     * @param string $email メールアドレス
     * @param string $password パスワード
     * @param Request $request リクエストオブジェクト（ログイン試行回数管理用）
     * @return array JWTトークン情報
     * @throws TooManyRequestsException ログイン試行回数超過時
     * @throws NoAccountException アカウントが存在しない場合
     * @throws AuthenticationException 認証失敗時
     */
    public function execute(string $email, string $password, Request $request): array
    {
        $key = 'login.' . $request->ip() . '.' . $email;

        // ログイン試行回数チェック
        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            throw new TooManyRequestsException();
        }

        // ユーザー存在チェック
        $user = User::byEmail($email)->first();
        if (!$user) {
            throw new NoAccountException();
        }

        // パスワード検証
        if (!Hash::check($password, $user->password)) {
            RateLimiter::hit($key, self::LOCKOUT_DECAY_MINUTES * 60);
            if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
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

        // JWTトークンを生成
        return $this->jwtService->issueJwt($user, Flag::TRUE);
    }
}

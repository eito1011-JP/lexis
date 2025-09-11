<?php

declare(strict_types=1);

namespace App\Guards;

use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class CookieGuard implements Guard
{
    protected UserProvider $provider;

    protected Request $request;

    protected ?User $user = null;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * ユーザーが認証済みかチェック
     */
    public function check(): bool
    {
        return ! is_null($this->user());
    }

    /**
     * ユーザーが認証済みかチェック（ゲスト判定）
     */
    public function guest(): bool
    {
        return ! $this->check();
    }

    /**
     * 認証済みユーザーを取得
     */
    public function user(): ?User
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->cookie('sid');
        if (! $token) {
            Log::debug('Cookie authentication: no sid cookie found');

            return null;
        }

        try {
            // クッキーから暗号化されたペイロードを復号化
            $payload = Crypt::decryptString($token);
            $data = json_decode($payload, true);

            if (! $data || ! isset($data['uid']) || ! isset($data['exp'])) {
                Log::debug('Cookie authentication: invalid payload structure', ['data' => $data]);

                return null;
            }

            // トークンの有効期限をチェック
            if (time() > $data['exp']) {
                Log::debug('Cookie authentication: token expired');

                return null;
            }

            // ユーザーを取得
            $this->user = $this->provider->retrieveById($data['uid']);

            if ($this->user) {
                Log::debug('Cookie authentication: user authenticated', ['user_id' => $this->user->id]);
            } else {
                Log::debug('Cookie authentication: user not found', ['uid' => $data['uid']]);
            }

            return $this->user;
        } catch (\Exception $e) {
            Log::warning('Cookie authentication failed', [
                'error' => $e->getMessage(),
                'cookie_exists' => ! empty($token),
            ]);

            return null;
        }
    }

    /**
     * ユーザーIDを取得
     */
    public function id(): ?int
    {
        $user = $this->user();

        return $user?->getKey();
    }

    /**
     * 認証情報によりユーザーを検証（通常の認証フロー用）
     */
    public function validate(array $credentials = []): bool
    {
        // このガードではクッキーベースの認証のみをサポート
        return false;
    }

    /**
     * ユーザーを設定（通常は使用しない）
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * ユーザーをガードにログイン（通常は使用しない）
     */
    public function login($user): void
    {
        $this->setUser($user);
    }

    /**
     * ログアウト（クッキーの削除は別途必要）
     */
    public function logout(): void
    {
        $this->user = null;
    }

    /**
     * ユーザーが設定されているかチェック
     */
    public function hasUser(): bool
    {
        return ! is_null($this->user);
    }
}

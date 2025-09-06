<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use Illuminate\Support\Facades\Cookie;

class LogoutUseCase
{
    public function __construct() {}

    /**
     * ログアウト処理を実行
     *
     * @return array レスポンスデータとクッキー情報
     */
    public function execute(): array
    {
        // クッキーを無効化
        $cookie = Cookie::forget('sid');

        return [
            'cookie' => $cookie,
        ];
    }
}

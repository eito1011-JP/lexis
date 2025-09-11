<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

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
        try {
            // クッキーを無効化
            $cookie = Cookie::forget('sid');

            return [
                'cookie' => $cookie,
            ];
        } catch (\Exception $e) {
            Log::error($e);

            throw $e;
        }
    }
}

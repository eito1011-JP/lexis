<?php

namespace App\Enums;

enum PullRequestActivityAction: string
{
    case FIX_REQUEST_SENT = 'fix_request_sent';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::FIX_REQUEST_SENT => '修正リクエスト送信',
        };
    }

    /**
     * 全ての値を取得
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum PullRequestStatus: string
{
    case OPENED = 'opened';
    case MERGED = 'merged';
    case CLOSED = 'closed';
    case CONFLICT = 'conflict';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::OPENED => 'オープン',
            self::MERGED => 'マージ済み',
            self::CLOSED => 'クローズ済み',
            self::CONFLICT => 'コンフリクト',
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

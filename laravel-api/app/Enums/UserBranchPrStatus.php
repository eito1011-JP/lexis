<?php

namespace App\Enums;

enum UserBranchPrStatus: string
{
    case NONE = 'none';
    case CONFLICT = 'conflict';
    case OPENED = 'opened';
    case MERGED = 'merged';
    case CLOSED = 'closed';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::NONE => 'なし',
            self::CONFLICT => 'コンフリクト',
            self::OPENED => 'オープン',
            self::MERGED => 'マージ済み',
            self::CLOSED => 'クローズ済み',
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

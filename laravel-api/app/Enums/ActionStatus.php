<?php

namespace App\Enums;

enum ActionStatus: string
{
    case PENDING = 'pending';
    case FIX_REQUESTED = 'fix_requested';
    case APPROVED = 'approved';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PENDING => '未対応',
            self::FIX_REQUESTED => '修正依頼',
            self::APPROVED => '承認済み',
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

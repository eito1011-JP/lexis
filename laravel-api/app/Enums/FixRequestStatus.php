<?php

namespace App\Enums;

enum FixRequestStatus: string
{
    case PENDING = 'pending';
    case APPLIED = 'applied';
    case ARCHIVED = 'archived';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PENDING => '未適用',
            self::APPLIED => '適用済み',
            self::ARCHIVED => 'アーカイブ',
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

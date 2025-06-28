<?php

namespace App\Enums;

enum DocumentCategoryStatus: string
{
    case DRAFT = 'draft';
    case PUSHED = 'pushed';
    case APPROVED = 'approved';
    case MERGED = 'merged';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::DRAFT => '下書き',
            self::PUSHED => 'プッシュ済み',
            self::APPROVED => '承認済み',
            self::MERGED => 'マージ済み',
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

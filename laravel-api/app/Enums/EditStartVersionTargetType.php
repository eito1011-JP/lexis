<?php

namespace App\Enums;

enum EditStartVersionTargetType: string
{
    case DOCUMENT = 'document';
    case CATEGORY = 'category';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::DOCUMENT => 'ドキュメント',
            self::CATEGORY => 'カテゴリ',
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

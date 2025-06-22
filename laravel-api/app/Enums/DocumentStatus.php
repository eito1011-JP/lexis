<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'draft';
    case COMMITTED = 'committed';
    case PUSHED = 'pushed';
    case MERGED = 'merged';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::DRAFT => '編集中',
            self::COMMITTED => 'コミット済み',
            self::PUSHED => 'レビュー中',
            self::MERGED => '完了',
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

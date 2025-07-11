<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case EDITOR = 'editor';

    /**
     * 管理者権限を持つロールかどうかを判定
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * オーナー権限を持つロールかどうかを判定
     */
    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * 編集者権限を持つロールかどうかを判定
     */
    public function isEditor(): bool
    {
        return $this === self::EDITOR;
    }

    /**
     * 表示用のラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'オーナー',
            self::ADMIN => '管理者',
            self::EDITOR => '編集者',
        };
    }
}

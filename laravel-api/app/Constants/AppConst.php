<?php

namespace App\Constants;

class AppConst
{
    // JWTリフレッシュトークン文字数
    public const JWT_REFRESH_TOKEN_LENGTH = 40;

    // Eメール認証トークン文字数
    public const EMAIL_AUTHN_TOKEN_LENGTH = 40;

    // 本人確認ステップ有無
    public const IDENTIFICATION_SCREENING = 0;

    // 本人確認後ユーザー情報設定ステップ有無
    public const SETUP_AFTER_IDENTIFICATION = 0;

    // Eメール認証トークン有効期間（分）
    public const EMAIL_AUTHN_TOKEN_TTL = 30;

    // Eメール変更トークン有効期間（分）
    public const EMAIL_VERIFICATION_TOKEN_TTL = 30;

    // パスワードリセットトークン有効期間（分）
    public const PASSWORD_RESET_TOKEN_TTL = 30;
}

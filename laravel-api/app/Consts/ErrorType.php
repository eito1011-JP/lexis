<?php

declare(strict_types=1);

namespace App\Consts;

/**
 * ErrorType constant.
 */
class ErrorType
{
    // Error messages are defined in errors.php in /resources/lang/{lang} directory.

    // Invalid request.
    // 不正なリクエストです
    public const CODE_INVALID_REQUEST = 'INVALID_REQUEST';

    public const STATUS_INVALID_REQUEST = 400;

    // Authentication failed.
    // 認証に失敗しました
    public const CODE_AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';

    public const STATUS_AUTHENTICATION_FAILED = 401;

    // Token has expired.
    // 認証トークンの有効期限が切れています
    public const CODE_TOKEN_EXPIRED = 'TOKEN_EXPIRED';

    public const STATUS_TOKEN_EXPIRED = 401;

    // Token is invalid.
    // 認証トークンが無効です
    public const CODE_INVALID_TOKEN = 'INVALID_TOKEN';

    public const STATUS_INVALID_TOKEN = 401;

    // Not authorized.
    // 権限がありません
    public const CODE_NOT_AUTHORIZED = 'NOT_AUTHORIZED';

    public const STATUS_NOT_AUTHORIZED = 403;

    // Resouce not found.
    // リソースが見つかりません
    public const CODE_NOT_FOUND = 'NOT_FOUND';

    public const STATUS_NOT_FOUND = 404;

    // Account not found.
    // アカウントは存在しません
    public const CODE_NO_ACCOUNT = 'NO_ACCOUNT';

    public const STATUS_NO_ACCOUNT = 404;

    // No data found.
    // データが存在しません
    public const CODE_NO_DATA = 'NO_DATA';

    public const STATUS_NO_DATA = 404;

    // Invalid HTTP method.
    // HTTPメソッドが不正です
    public const CODE_METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';

    public const STATUS_METHOD_NOT_ALLOWED = 405;

    // This process has been already executed.
    // この操作はすでに実行されています
    public const CODE_DUPLICATE_EXECUTION = 'DUPLICATE_EXECUTION';

    public const STATUS_DUPLICATE_EXECUTION = 409;

    // The account cannot be registered.
    // このアカウントは登録できません
    public const CODE_ACCOUNT_CANNOT_BE_REGISTERED = 'ACCOUNT_CANNOT_BE_REGISTERED';

    public const STATUS_ACCOUNT_CANNOT_BE_REGISTERED = 409;

    // Validation error.
    // /resources/lang/ja/validation.php messages are used
    public const CODE_INVALID_PARAMETERS = 'INVALID_PARAMETERS';

    public const STATUS_INVALID_PARAMETERS = 422;

    // Could not be executed.
    // 実行できませんでした
    public const CODE_BUSINESS_RULE_VIOLATION = 'BUSINESS_RULE_VIOLATION';

    public const STATUS_BUSINESS_RULE_VIOLATION = 422;

    // Another user is operating.
    // 他のユーザが操作中です
    public const CODE_LOCK_ACQUISITION_TIMEOUT = 'LOCK_ACQUISITION_TIMEOUT';

    public const STATUS_LOCK_ACQUISITION_TIMEOUT = 423;

    // The maximum number of attempts has been reached. Please try again in a few minutes.
    // 試行回数の上限に達しました。しばらくしてから再度お試しください。
    public const CODE_TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';

    public const STATUS_TOO_MANY_REQUESTS = 429;

    // An unexpected error has occurred.
    // 予期せぬエラーが発生しました
    public const CODE_INTERNAL_ERROR = 'INTERNAL_ERROR';

    public const STATUS_INTERNAL_ERROR = 500;

    // A database error has occurred.
    // データベースエラーが発生しました
    public const CODE_DB_ERROR = 'DB_ERROR';

    public const STATUS_DB_ERROR = 500;

    // Settlement failed.
    // 決済に失敗しました
    public const CODE_SETTLEMENT_ERROR = 'SETTLEMENT_ERROR';

    public const STATUS_SETTLEMENT_ERROR = 500;

    // The service is temporarily unavailable.
    // 一時的にサービスがご利用できません
    public const CODE_OVER_CAPACITY = 'OVER_CAPACITY';

    public const STATUS_OVER_CAPACITY = 503;

    // We're under maintenance.
    // ただいまメンテナンス中です
    public const CODE_UNDER_MAINTENANCE = 'UNDER_MAINTENANCE';

    public const STATUS_UNDER_MAINTENANCE = 503;
}
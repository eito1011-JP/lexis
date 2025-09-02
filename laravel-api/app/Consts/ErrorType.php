<?php

declare(strict_types=1);

namespace App\Consts;

/**
 * Error type constants.
 */
class ErrorType
{
    // Error codes
    public const CODE_TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';
    public const CODE_NO_ACCOUNT = 'NO_ACCOUNT';
    public const CODE_AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';
    public const CODE_DOCUMENT_NOT_FOUND = 'DOCUMENT_NOT_FOUND';
    public const CODE_DUPLICATE_EXECUTION = 'DUPLICATE_EXECUTION';
    public const CODE_TARGET_DOCUMENT_NOT_FOUND = 'TARGET_DOCUMENT_NOT_FOUND';
    public const CODE_DATABASE_ERROR = 'DATABASE_ERROR';

    // HTTP status codes
    public const STATUS_TOO_MANY_REQUESTS = 429;
    public const STATUS_NO_ACCOUNT = 404;
    public const STATUS_AUTHENTICATION_FAILED = 401;
    public const STATUS_DOCUMENT_NOT_FOUND = 404;
    public const STATUS_DUPLICATE_EXECUTION = 409;
    public const STATUS_TARGET_DOCUMENT_NOT_FOUND = 409;
    public const STATUS_DATABASE_ERROR = 500;
    public const STATUS_INTERNAL_ERROR = 500;
}

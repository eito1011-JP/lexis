<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Illuminate\Http\JsonResponse;

/**
 * 認証系エラーの例外
 */
class AuthenticationException extends BaseException
{
    public function toResponse($request): JsonResponse
    {
        $this->setErrorCode(ErrorType::CODE_AUTHENTICATION_FAILED);
        $this->setErrorMessage(__('errors.MSG_AUTHENTICATION_FAILED'));
        $this->setStatusCode(ErrorType::STATUS_AUTHENTICATION_FAILED);

        return parent::toResponse($request);
    }
}



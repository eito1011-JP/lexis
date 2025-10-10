<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Illuminate\Http\JsonResponse;

/**
 * 権限不足エラーの例外
 */
class NotAuthorizedException extends BaseException
{
    public function toResponse($request): JsonResponse
    {
        $this->setErrorCode(ErrorType::CODE_NOT_AUTHORIZED);
        $this->setErrorMessage(__('errors.MSG_NOT_AUTHORIZED'));
        $this->setStatusCode(ErrorType::STATUS_NOT_AUTHORIZED);

        return parent::toResponse($request);
    }
}

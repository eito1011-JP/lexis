<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Illuminate\Http\JsonResponse;

class NoAccountException extends BaseException
{
    public function toResponse($request): JsonResponse
    {
        $this->setErrorCode(ErrorType::CODE_NO_ACCOUNT);
        $this->setErrorMessage(__('errors.MSG_NO_ACCOUNT'));
        $this->setStatusCode(ErrorType::STATUS_NO_ACCOUNT);

        return parent::toResponse($request);
    }
}

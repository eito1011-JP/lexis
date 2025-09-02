<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Illuminate\Http\JsonResponse;

class TooManyRequestsException extends BaseException
{
    public function toResponse($request): JsonResponse
    {
        $this->setErrorCode(ErrorType::CODE_TOO_MANY_REQUESTS);
        $this->setErrorMessage(__('errors.MSG_TOO_MANY_REQUESTS'));
        $this->setStatusCode(ErrorType::STATUS_TOO_MANY_REQUESTS);

        return parent::toResponse($request);
    }
}

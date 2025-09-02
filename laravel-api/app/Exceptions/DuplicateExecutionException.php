<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Illuminate\Http\JsonResponse;

class DuplicateExecutionException extends BaseException
{
    public function toResponse($request): JsonResponse
    {
        $this->setErrorCode(ErrorType::CODE_DUPLICATE_EXECUTION);
        $this->setErrorMessage(__('errors.MSG_DUPLICATE_EXECUTION'));
        $this->setStatusCode(ErrorType::STATUS_DUPLICATE_EXECUTION);

        return parent::toResponse($request);
    }
}

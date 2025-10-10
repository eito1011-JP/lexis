<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Illuminate\Http\JsonResponse;

/**
 * リソース未発見エラーの例外
 */
class NotFoundException extends BaseException
{
    public function toResponse($request): JsonResponse
    {
        $this->setErrorCode(ErrorType::CODE_NOT_FOUND);
        $this->setErrorMessage(__('errors.MSG_NOT_FOUND'));
        $this->setStatusCode(ErrorType::STATUS_NOT_FOUND);

        return parent::toResponse($request);
    }
}

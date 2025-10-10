<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Database exception class.
 */
class DBException extends BaseException
{
    public function __construct(?Exception $exception = null)
    {
        if ($exception != null) {
            Log::error($exception->getMessage());
        }
    }

    public function toResponse($request): JsonResponse
    {
        $this->setErrorCode(ErrorType::CODE_DB_ERROR);
        $this->setErrorMessage(__('errors.MSG_DB_ERROR'));
        $this->setStatusCode(ErrorType::STATUS_DB_ERROR);

        return parent::toResponse($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class BaseException extends RuntimeException implements Responsable
{
    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var int
     */
    protected $status;

    /**
     * BaseException constructor.
     */
    public function __construct(string $code = '', string $message = '', int $status = ErrorType::STATUS_INTERNAL_ERROR)
    {
        $this->code = $code;
        $this->message = $message;
        $this->status = $status;
    }

    public function setErrorCode(string $code): void
    {
        $this->code = $code;
    }

    public function getErrorCode(): string
    {
        return $this->code;
    }

    public function setErrorMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getErrorMessage(): string
    {
        return $this->message;
    }

    public function setStatusCode(int $status): void
    {
        $this->status = $status;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function toResponse($request): JsonResponse
    {
        return response()->json($this->getBasicResponse(), $this->getStatusCode());
    }

    protected function getBasicResponse()
    {
        return [
            'error' => [
                'code' => $this->getErrorCode(),
                'message' => $this->getErrorMessage(),
            ],
        ];
    }
}

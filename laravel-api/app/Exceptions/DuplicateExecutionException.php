<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DuplicateExecutionException extends Exception
{
    public function __construct(
        public string $codeString,
        string $message,
        public string $statusString
    ) {
        parent::__construct($message);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->codeString,
                'message' => $this->getMessage(),
            ],
        ], 409);
    }
}

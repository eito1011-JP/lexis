<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * 認証系エラーの例外
 */
class AuthenticationException extends Exception
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
        ], 401);
    }
}



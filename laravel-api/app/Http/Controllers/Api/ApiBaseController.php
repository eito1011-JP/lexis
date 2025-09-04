<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\BaseEncoding;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class ApiBaseController extends Controller
{
    use BaseEncoding;

    /**
     * Get the authenticated user.
     */
    protected function user(): ?User
    {
        return Auth::guard('api')->user();
    }

    protected function sendError($code, $message, $status, $logLevel = null): JsonResponse
    {
        // Output log.
        if ($logLevel !== null) {
            // Log context
            $context = $this->getContext($code);

            if ($logLevel == LogLevel::DEBUG) {
                Log::debug($message, $context);
            } elseif ($logLevel == LogLevel::INFO) {
                Log::info($message, $context);
            } elseif ($logLevel == LogLevel::WARNING) {
                Log::warning($message, $context);
            } elseif ($logLevel == LogLevel::ERROR) {
                Log::error($message, $context);
            } elseif ($logLevel == LogLevel::ALERT) {
                Log::alert($message, $context);
            }
        }

        $response = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        return response()->json($response, $status);
    }

    /**
     * Write debug log.
     */
    protected function logDebug($message): void
    {
        // Log context
        $context = $this->getContext();

        Log::debug($message, $context);
    }

    /**
     * Write info log.
     */
    protected function logInfo($message): void
    {
        // Log context
        $context = $this->getContext();

        Log::info($message, $context);
    }

    /**
     * Write warning log.
     */
    protected function logWarning($message): void
    {
        // Log context
        $context = $this->getContext();

        Log::warning($message, $context);
    }

    /**
     * Get log context.
     */
    private function getContext($code = null): array
    {
        if ($code) {
            return $context = [
                'code' => $code,
                'user_id' => Auth::check() ? $this->user()->user_id : null,
                'input' => request()->all(),
            ];
        }

        return $context = [
            'user_id' => Auth::check() ? $this->user()->user_id : null,
            'input' => request()->all(),
        ];
    }
}

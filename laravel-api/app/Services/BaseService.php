<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BaseService
{
    public function __construct()
    {
    }

    /**
     * Write debug log.
     *
     * @param  string  $message
     * @param  null|string  $code
     */
    protected function logDebug($message, $code = null): void
    {
        // Log context
        Log::withContext([
            'code' => $code,
            ...$this->getLogCallerInfo(),
        ]);

        Log::debug($message);
    }

    /**
     * Write info log.
     *
     * @param  string  $message
     * @param  null|string  $code
     */
    protected function logInfo($message, $code = null): void
    {
        // Log context
        Log::withContext([
            'code' => $code,
            ...$this->getLogCallerInfo(),
        ]);

        Log::info($message);
    }

    /**
     * Write warning log.
     *
     * @param  string  $message
     * @param  null|string  $code
     */
    protected function logWarning($message, $code = null): void
    {
        // Log context
        Log::withContext([
            'code' => $code,
            ...$this->getLogCallerInfo(),
        ]);

        Log::warning($message);
    }

    /**
     * Get log caller info.
     *
     * @return array
     */
    private function getLogCallerInfo()
    {
        return [
            'call' => debug_backtrace()[1]['file'] . '->' . debug_backtrace()[2]['function'] . ':' . debug_backtrace()[1]['line'],
            'called_arg' => debug_backtrace()[2]['args'],
            'parent_call' => debug_backtrace()[2]['file'] . '->' . debug_backtrace()[3]['function'] . ':' . debug_backtrace()[2]['line'],
            'parent_called_arg' => debug_backtrace()[3]['args'],
        ];
    }
}
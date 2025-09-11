<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ヘルスチェック用コントローラー
 * EC2内でAPIの死活確認を行うためのエンドポイント
 */
class HealthCheckController extends ApiBaseController
{
    /**
     * ヘルスチェックエンドポイント
     */
    public function healthCheck(): JsonResponse
    {
        try {
            // 基本的なアプリケーション状態確認
            $status = [
                'status' => 'ok',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
            ];

            // データベース接続確認
            try {
                DB::connection()->getPdo();
                $status['database'] = 'connected';
            } catch (\Exception $e) {
                Log::error('Database connection failed in health check', [
                    'error' => $e->getMessage(),
                ]);
                $status['database'] = 'disconnected';
                $status['status'] = 'error';
            }

            // レスポンスコードを決定
            $httpStatus = $status['status'] === 'ok' ? 200 : 503;

            return response()->json($status, $httpStatus);

        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'timestamp' => now()->toISOString(),
                'error' => 'Internal server error',
            ], 500);
        }
    }
}

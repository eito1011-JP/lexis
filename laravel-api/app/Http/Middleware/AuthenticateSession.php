<?php

namespace App\Http\Middleware;

use App\Models\Session;
use Closure;
use Illuminate\Http\Request;

class AuthenticateSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->cookie('sid');

        if (! $sessionId) {
            return response()->json([
                'error' => '認証が必要です',
            ], 401);
        }

        $user = $this->getSessionUser($sessionId);

        if (! $user) {
            return response()->json([
                'error' => '無効なセッションです',
            ], 401);
        }

        // リクエストにユーザー情報を追加
        $request->merge(['user' => $user]);

        return $next($request);
    }

    /**
     * セッションからユーザー情報を取得
     */
    private function getSessionUser(string $sessionId): ?array
    {
        $session = Session::where('id', $sessionId)
            ->where('expired_at', '>', now())
            ->first();

        if (! $session) {
            return null;
        }

        $sessionData = json_decode($session->sess, true);

        return [
            'userId' => $sessionData['userId'],
            'email' => $sessionData['email'],
        ];
    }
}

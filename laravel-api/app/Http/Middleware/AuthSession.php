<?php

namespace App\Http\Middleware;

use App\Models\Session;
use Closure;
use Illuminate\Http\Request;

class AuthSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->cookie('sid');

        if (! $sessionId) {
            return response()->json([
                'error' => 'セッションがありません',
            ], 401);
        }

        $session = Session::where('id', $sessionId)
            ->where('expired_at', '>', now())
            ->first();

        if (! $session) {
            return response()->json([
                'error' => 'セッションが無効または期限切れです',
            ], 401);
        }

        $sessionData = json_decode($session->sess, true);

        // ユーザー情報をリクエストに追加
        $request->merge([
            'user' => [
                'userId' => $sessionData['userId'],
                'email' => $sessionData['email'],
            ],
        ]);

        return $next($request);
    }
}

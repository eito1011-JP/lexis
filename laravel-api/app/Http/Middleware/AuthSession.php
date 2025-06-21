<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Session;

class AuthSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->cookie('sid');

        if (!$sessionId) {
            return response()->json([
                'error' => 'セッションがありません'
            ], 401);
        }

        $session = Session::where('session_id', $sessionId)
            ->where('expire_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json([
                'error' => 'セッションが無効または期限切れです'
            ], 401);
        }

        // ユーザー情報をリクエストに追加
        $request->merge([
            'user' => [
                'userId' => $session->user_id,
                'email' => $session->email,
            ]
        ]);

        return $next($request);
    }
} 
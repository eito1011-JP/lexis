<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class JwtService extends BaseService
{
    /**
     * Generate access token (Sanctum Personal Access Token).
     */
    public function issueJwt(User $user): array
    {
        // TTL（分）を取得。sanctum.expiration を使用（デフォルト60分）
        $ttlMinutes = (int) config('sanctum.expiration', 60);
        
        // 有効期限を計算
        $expiresAt = Carbon::now()->addMinutes($ttlMinutes);
        
        // アクセストークン（Sanctum Personal Access Token）を有効期限付きで発行
        $tokenResult = $user->createToken('login-token', ['*'], $expiresAt);
        $accessToken = $tokenResult->plainTextToken;

        return [
            'token' => $accessToken,
            'token_type' => 'bearer',
            'expires_at' => $expiresAt->toISOString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AppConst;
use App\Consts\Flag;
use App\Exceptions\DBException;
use App\Models\RefreshToken;
use App\Models\User;
use Exception;
use Illuminate\Support\Str;

class JwtService extends BaseService
{
    /**
     * Generate JWT and refresh token.
     */
    public function issueJwt(User $user): array
    {
        // Generate a refresh token and store in database
        $refreshToken = $this->generateRefreshToken($user->id);

        // Generate JWT (仮実装 - 実際のJWTライブラリの実装に依存)
        $jwtResponse = [
            'token' => 'jwt_token_placeholder', // JWTAuth::fromUser($user) の代替
            'token_type' => 'bearer',
            'expires_at' => config('jwt.ttl', 60) * 60,
            'refresh_token' => $refreshToken,
        ];

        return $jwtResponse;
    }

    /**
     * Generate a refresh token.
     *
     * @return string
     */
    public function generateRefreshToken(int $userId): string
    {
        // Generate a refresh token and encrypt it
        $refreshToken = Str::random(AppConst::JWT_REFRESH_TOKEN_LENGTH);
        $encryptedRefreshToken = hash('sha256', $refreshToken);

        // Store the encrypted refresh token in database
        try {
            RefreshToken::create([
                'user_id' => $userId,
                'hashed_refresh_token' => $encryptedRefreshToken,
                'expired_at' => now()->addMinute(config('jwt.refresh_ttl')),
                'is_blacklisted' => Flag::FALSE,
                'blacklisted_at' => null,
            ]);
        } catch (Exception $e) {
            throw new DBException();
        }

        return $refreshToken;
    }
}

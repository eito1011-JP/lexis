<?php

namespace App\Repositories;

use App\Models\PreUser;
use App\Repositories\Interfaces\PreUserRepositoryInterface;
use Carbon\Carbon;

class PreUserRepository implements PreUserRepositoryInterface
{
    public function updateInvalidated(string $email): int
        {
            return PreUser::byEmail($email)
            ->isInvalidated(false)
            ->update([
                'is_invalidated' => true,
                'invalidated_at' => Carbon::now(),
            ]);
    }

    public function registerPreUser(string $email, string $password, string $token, ?\DateTimeInterface $expiredAt = null): PreUser
    {
        return PreUser::create([
            'email' => $email,
            'password' => $password,
            'token' => $token,
            'expired_at' => $expiredAt ? Carbon::instance(Carbon::parse($expiredAt->format('c'))) : Carbon::now()->addMinutes(30),
            'is_invalidated' => false,
        ]);
    }

    public function findActiveByEmail(string $email): ?PreUser
    {
        return PreUser::byEmail($email)
            ->isInvalidated(false)
            ->first();
    }

    public function findActiveByToken(string $token): ?PreUser
    {
        return PreUser::byToken($token)
            ->isInvalidated(false)
            ->whereExpiredAt()
            ->first();
    }
}



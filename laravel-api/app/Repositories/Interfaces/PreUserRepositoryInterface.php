<?php

namespace App\Repositories\Interfaces;

use App\Models\PreUser;

interface PreUserRepositoryInterface
{
    public function updateInvalidated(string $email): int;

    public function registerPreUser(string $email, string $password, string $token): PreUser;

    public function findActiveByEmail(string $email): ?PreUser;

    public function findActiveByToken(string $token): ?PreUser;
}



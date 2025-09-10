<?php

namespace App\UseCases\Auth;

use App\Consts\ErrorType;
use App\Exceptions\AuthenticationException;
use App\Repositories\Interfaces\PreUserRepositoryInterface;
use Illuminate\Support\Facades\Log;

class IdentifyTokenUseCase
{
    public function __construct(
        private PreUserRepositoryInterface $preUserRepository
    ) {}

    /**
     * @return array{valid: bool}
     */
    public function execute(string $token): array
    {
        try {
        $preUser = $this->preUserRepository->findActiveByToken($token);

        if (!$preUser) {
            throw new AuthenticationException(
                ErrorType::CODE_INVALID_TOKEN,
                __('errors.MSG_INVALID_TOKEN'),
                ErrorType::STATUS_INVALID_TOKEN,
            );
        }
        } catch (AuthenticationException $e) {
            Log::error($e);
            throw $e;
        }

        return ['valid' => true];
    }
}



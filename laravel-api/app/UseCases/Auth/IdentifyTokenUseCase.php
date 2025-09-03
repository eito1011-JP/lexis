<?php

namespace App\UseCases\Auth;

use App\Consts\ErrorType;
use App\Exceptions\AuthenticationException;
use App\Repositories\Interfaces\PreUserRepositoryInterface;

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
        $preUser = $this->preUserRepository->findActiveByToken($token);

        if (!$preUser) {
            throw new AuthenticationException(
                ErrorType::CODE_INVALID_TOKEN,
                __('errors.MSG_INVALID_TOKEN'),
                ErrorType::STATUS_INVALID_TOKEN,
            );
        }

        return ['valid' => true];
    }
}



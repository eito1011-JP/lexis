<?php

namespace App\UseCases\Organization;

use App\Consts\Flag;
use App\Enums\ErrorType;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Repositories\Interfaces\PreUserRepositoryInterface;

class CreateOrganizationUseCase
{
    public function __construct(
        private PreUserRepositoryInterface $preUserRepository
    ) {}

    /**
     * @return array{organization: Organization, user: User}
     */
    public function execute(string $organizationUuid, string $organizationName, string $token): array
    {
        $preUser = $this->preUserRepository->findActiveByToken($token);

        if (!$preUser) {
            throw new AuthenticationException(
                ErrorType::CODE_INVALID_TOKEN->value,
                __('errors.MSG_INVALID_TOKEN'),
                ErrorType::STATUS_INVALID_TOKEN->value,
            );
        }

        $exists = Organization::query()
            ->where('uuid', $organizationUuid)
            ->orWhere('name', $organizationName)
            ->exists();

        if ($exists) {
            throw new DuplicateExecutionException(
                'CODE_ORGANIZATION_ALREADY_EXISTS',
                __('errors.MSG_ORGANIZATION_ALREADY_EXISTS'),
                'STATUS_ORGANIZATION_ALREADY_EXISTS',
            );
        }

        $user = User::create([
            'email' => $preUser->email,
            'password' => $preUser->password,
            'nickname' => null,
        ]);

        $organization = Organization::create([
            'uuid' => $organizationUuid,
            'name' => $organizationName,
        ]);

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);

        $preUser->update([
            'is_invalidated' => Flag::TRUE,
        ]);

        return ['organization' => $organization, 'user' => $user];
    }
}



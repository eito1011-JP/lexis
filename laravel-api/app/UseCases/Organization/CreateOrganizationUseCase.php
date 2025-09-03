<?php

namespace App\UseCases\Organization;

use App\Consts\Flag;
use App\Consts\ErrorType;
use App\Enums\OrganizationRoleBindingRole;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationRoleBinding;
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
                ErrorType::CODE_INVALID_TOKEN,
                __('errors.MSG_INVALID_TOKEN'),
                ErrorType::STATUS_INVALID_TOKEN,
            );
        }

        $exists = Organization::query()
            ->where('uuid', $organizationUuid)
            ->exists();

        if ($exists) {
            throw new DuplicateExecutionException(
                ErrorType::CODE_DUPLICATE_EXECUTION,
                __('errors.MSG_DUPLICATE_EXECUTION'),
                ErrorType::STATUS_DUPLICATE_EXECUTION,
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

        OrganizationRoleBinding::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationRoleBindingRole::OWNER->value,
        ]);

        $preUser->update([
            'is_invalidated' => Flag::TRUE,
        ]);

        return ['organization' => $organization, 'user' => $user];
    }
}



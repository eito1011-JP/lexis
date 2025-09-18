<?php

namespace App\Policies;

use App\Enums\OrganizationRoleBindingRole;
use App\Models\OrganizationRoleBinding;
use App\Models\PullRequest;

class PullRequestPolicy
{
    /**
     * プルリクエストをマージする権限があるかを判定
     */
    public function merge(int $userId, PullRequest $pullRequest): bool
    {
        // ユーザーがプルリクエストの組織でadmin以上の権限を持っているかを確認
        $roleBinding = OrganizationRoleBinding::where('user_id', $userId)
            ->where('organization_id', $pullRequest->organization_id)
            ->first();

        if (! $roleBinding) {
            return false;
        }

        $role = OrganizationRoleBindingRole::from($roleBinding->role);

        return $role->isAdmin() || $role->isOwner();
    }
}

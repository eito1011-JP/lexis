<?php

namespace App\UseCases\User;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserBranch;
use Http\Discovery\Exception\NotFoundException;

class UserMeUseCase
{
    /**
     * ユーザー情報、組織情報、ユーザーブランチ情報を取得
     *
     * @return array{user: User, organization: Organization|null, activeUserBranch: UserBranch|null}
     */
    public function execute(User $user): array
    {
        $userInfo = $user->with(['organizationMember.organization', 'userBranches'])->find($user->id);

        if (!$userInfo) {
            throw new NotFoundException();
        }   

        // 組織情報を取得
        $organization = $userInfo->organizationMember->organization;

        if (!$organization) {
            throw new NotFoundException();
        }

        // アクティブなユーザーブランチを取得
        $activeUserBranch = $userInfo->userBranches()->active()->first();

        return [
            'user' => $userInfo,
            'organization' => $organization,
            'activeUserBranch' => $activeUserBranch,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\OrganizationMember;
use Http\Discovery\Exception\NotFoundException;

/**
 * 組織関連のサービスクラス
 */
class OrganizationService extends BaseService
{
    /**
     * ユーザーが指定された組織に所属しているか確認し、所属していない場合は例外をスロー
     *
     * @param  int  $userId  確認対象のユーザーID
     * @param  int  $organizationId  組織ID
     *
     * @throws NotFoundException ユーザーが組織に所属していない場合
     */
    public function validateUserBelongsToOrganization(int $userId, int $organizationId): void
    {
        $organizationMember = OrganizationMember::where('user_id', $userId)->where('organization_id', $organizationId)->first();

        if (! $organizationMember) {
            throw new NotFoundException;
        }
    }
}

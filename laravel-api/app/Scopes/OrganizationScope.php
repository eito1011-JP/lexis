<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Organization scope to filter queries by organization_id.
 */
class OrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // リクエストからorganization_idを取得
        $organizationId = $this->getOrganizationIdFromRequest();

        if ($organizationId !== null) {
            $builder->where($model->getTable().'.organization_id', $organizationId);
        }
    }

    /**
     * リクエストからorganization_idを取得
     */
    private function getOrganizationIdFromRequest(): ?int
    {
        $request = app('request');

        // middlewareで設定されたorganization_idを取得
        return $request->attributes->get('organization_id');
    }
}

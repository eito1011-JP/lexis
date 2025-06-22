<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Consts\Flag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Soft deleting scope.
 */
class SoftDeletingScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable().'.is_deleted', Flag::FALSE);
    }
}

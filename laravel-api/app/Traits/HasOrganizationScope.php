<?php

declare(strict_types=1);

namespace App\Traits;

use App\Scopes\OrganizationScope;

/**
 * Organization scope trait.
 * 
 * このtraitを使用することで、モデルに自動的にOrganizationScopeが適用されます。
 */
trait HasOrganizationScope
{
    /**
     * The "booted" method of the model.
     */
    protected static function bootHasOrganizationScope(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }
}

<?php

namespace App\Providers;

use App\Repositories\DocumentVersionRepository;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Repositories\Interfaces\PreUserRepositoryInterface;
use App\Repositories\PreUserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Repository バインディング専用のサービスプロバイダー
 *
 * すべてのRepositoryインターフェースと実装クラスのバインディングを管理
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register any repository services.
     */
    public function register(): void
    {
        // DocumentVersion Repository バインディング
        $this->app->bind(
            DocumentVersionRepositoryInterface::class,
            DocumentVersionRepository::class
        );

        // PreUser Repository バインディング
        $this->app->bind(
            PreUserRepositoryInterface::class,
            PreUserRepository::class
        );
    }

    /**
     * Bootstrap any repository services.
     */
    public function boot(): void
    {
        //
    }
}

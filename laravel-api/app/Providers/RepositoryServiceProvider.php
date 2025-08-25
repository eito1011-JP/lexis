<?php

namespace App\Providers;

use App\Repositories\Interfaces\PullRequestEditSessionRepositoryInterface;
use App\Repositories\PullRequestEditSessionRepository;
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
        // PullRequestEditSession Repository バインディング
        $this->app->bind(
            PullRequestEditSessionRepositoryInterface::class,
            PullRequestEditSessionRepository::class
        );

        // 今後追加するRepositoryのバインディングはここに追加
        // 例：
        // $this->app->bind(
        //     DocumentRepositoryInterface::class,
        //     DocumentRepository::class
        // );
    }

    /**
     * Bootstrap any repository services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Guards\CookieGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // カスタムクッキーガードを登録
        Auth::extend('cookie', function ($app, $name, array $config) {
            return new CookieGuard(
                Auth::createUserProvider($config['provider']),
                $app['request']
            );
        });
    }
}
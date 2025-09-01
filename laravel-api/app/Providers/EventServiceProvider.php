<?php

namespace App\Providers;

use App\Events\PreUserCreated;
use App\Listeners\SendEmailAuthentication;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        PreUserCreated::class => [
            SendEmailAuthentication::class,
        ],
    ];
}



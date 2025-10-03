<?php

namespace App\Providers;

use App\Services\UserService;
use App\Services\ClienteService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserService::class);
        $this->app->singleton(ClienteService::class);

    }

    public function boot(): void
    {
        //
    }
}
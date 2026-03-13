<?php

namespace App\Providers;

use App\Services\Sword\ConfParser;
use App\Services\Sword\ModuleInstaller;
use App\Services\Sword\RepositoryBrowser;
use App\Services\Sword\SwordManager;
use Illuminate\Support\ServiceProvider;

class SwordServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfParser::class);
        $this->app->singleton(SwordManager::class);

        $this->app->singleton(RepositoryBrowser::class, function ($app) {
            return new RepositoryBrowser($app->make(ConfParser::class));
        });

        $this->app->singleton(ModuleInstaller::class, function ($app) {
            return new ModuleInstaller($app->make(ConfParser::class));
        });
    }

    public function boot(): void
    {
        //
    }
}

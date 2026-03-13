<?php

namespace App\Providers;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CacheService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent lazy loading in non-production to catch N+1 queries early
        Model::preventLazyLoading(! $this->app->isProduction());

        // Prevent silently discarding attributes not in $fillable (dev only)
        Model::preventSilentlyDiscardingAttributes($this->app->environment('local'));

        // Log slow queries in production (> 100ms)
        if ($this->app->isProduction()) {
            DB::listen(function ($query) {
                if ($query->time > 100) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'time_ms' => $query->time,
                        'bindings' => $query->bindings,
                    ]);
                }
            });
        }
    }
}

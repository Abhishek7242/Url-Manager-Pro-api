<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\IndexNowService;

class IndexNowServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // bind IndexNowService lazily - DO NOT resolve here
        $this->app->singleton(IndexNowService::class, function ($app) {
            return new IndexNowService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // keep empty unless you need to perform runtime actions
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\Services\DownloadService;
use App\Services\UpdaterService;
use App\Services\UploadService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DownloadService::class, function ($app) {
            return new DownloadService();
        });

        $this->app->singleton(UpdaterService::class, function ($app) {
            return new UpdaterService();
        });

        $this->app->singleton(UploadService::class, function ($app) {
            return new UploadService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::componentNamespace('App\\View\\Components', 'app');
    }
}

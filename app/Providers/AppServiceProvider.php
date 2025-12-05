<?php

namespace App\Providers;

use App\Models\Media;
use App\Policies\MediaPolicy;
use App\Services\DownloadService;
use App\Services\UpdaterService;
use App\Services\UploadService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DownloadService::class, function ($app) {
            return new DownloadService;
        });

        $this->app->singleton(UpdaterService::class, function ($app) {
            return new UpdaterService;
        });

        $this->app->singleton(UploadService::class, function ($app) {
            return new UploadService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Media::class, MediaPolicy::class);

        // Log Livewire file upload failures for debugging
        Livewire::listen('component.dehydrate', function ($component, $response) {
            // Check for upload validation errors
            if (method_exists($component, 'getErrorBag')) {
                $errors = $component->getErrorBag();
                if ($errors->isNotEmpty()) {
                    foreach ($errors->all() as $error) {
                        if (str_contains($error, 'upload') || str_contains($error, 'file')) {
                            Log::warning('Livewire upload validation error', [
                                'component' => get_class($component),
                                'error' => $error,
                            ]);
                        }
                    }
                }
            }
        });
    }
}

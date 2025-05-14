<?php

declare(strict_types=1);

namespace Mirak\Lararestler;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lararestler.php',
            'lararestler'
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'lararestler');

        $this->publishes([
            __DIR__ . '/../config/lararestler.php' => config_path('lararestler.php'),
        ], "lararestler-config");

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/lararestler'),
        ], "lararestler-views");

        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/lararestler'),
        ], "lararestler-public");
    }
}

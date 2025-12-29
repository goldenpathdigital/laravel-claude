<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude;

use Illuminate\Support\ServiceProvider;

class ClaudeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/claude.php', 'claude');

        $this->app->singleton(ClaudeManager::class, function ($app) {
            return new ClaudeManager($app['config']['claude']);
        });

        $this->app->alias(ClaudeManager::class, 'claude');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/claude.php' => config_path('claude.php'),
            ], 'claude-config');
        }
    }
}

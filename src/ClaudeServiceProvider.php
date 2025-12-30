<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude;

use GoldenPathDigital\Claude\Contracts\ClaudeClientInterface;
use GoldenPathDigital\Claude\Exceptions\ConfigurationException;
use Illuminate\Support\ServiceProvider;

class ClaudeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/claude.php', 'claude');

        $this->app->singleton(ClaudeManager::class, function ($app) {
            $config = $app['config']['claude'];

            return new ClaudeManager($config);
        });

        $this->app->alias(ClaudeManager::class, 'claude');
        $this->app->alias(ClaudeManager::class, ClaudeClientInterface::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/claude.php' => config_path('claude.php'),
            ], 'claude-config');
        }

        $this->validateConfiguration();
    }

    protected function validateConfiguration(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $config = $this->app['config']['claude'];
        $apiKey = $config['api_key'] ?? null;
        $authToken = $config['auth_token'] ?? null;

        if (empty($apiKey) && empty($authToken)) {
            if ($this->app->environment('local', 'development', 'testing')) {
                return;
            }

            throw new ConfigurationException(
                'Laravel Claude requires authentication. Set ANTHROPIC_API_KEY or ANTHROPIC_AUTH_TOKEN in your .env file.'
            );
        }
    }
}

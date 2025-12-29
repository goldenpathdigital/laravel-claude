<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Tests;

use GoldenPathDigital\Claude\ClaudeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ClaudeServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Claude' => \GoldenPathDigital\Claude\Facades\Claude::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('claude.api_key', 'test-api-key');
        $app['config']->set('claude.default_model', 'claude-sonnet-4-5-20250929');
    }
}

<?php

declare(strict_types=1);

use GoldenPathDigital\Claude\ClaudeManager;
use GoldenPathDigital\Claude\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

afterEach(function () {
    ClaudeManager::clearFake();
});

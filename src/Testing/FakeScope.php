<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Testing;

use GoldenPathDigital\Claude\ClaudeManager;

class FakeScope
{
    protected PendingClaudeFake $fake;

    protected bool $disposed = false;

    public function __construct(PendingClaudeFake $fake)
    {
        $this->fake = $fake;
    }

    public function getFake(): PendingClaudeFake
    {
        return $this->fake;
    }

    public function dispose(): void
    {
        if ($this->disposed) {
            return;
        }

        ClaudeManager::clearFake();
        $this->disposed = true;
    }

    public function __destruct()
    {
        $this->dispose();
    }
}

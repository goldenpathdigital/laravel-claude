<?php

declare(strict_types=1);

namespace GoldenPathDigital\Claude\Exceptions;

use Throwable;

class RateLimitException extends ApiException
{
    protected ?int $retryAfterSeconds;

    public function __construct(
        string $message = 'Rate limit exceeded',
        ?int $retryAfterSeconds = null,
        ?Throwable $previous = null
    ) {
        $this->retryAfterSeconds = $retryAfterSeconds;
        parent::__construct($message, 'rate_limit_error', $previous);
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}

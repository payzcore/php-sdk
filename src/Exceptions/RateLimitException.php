<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

class RateLimitException extends PayzCoreException
{
    protected ?int $retryAfter;
    protected bool $isDaily;

    public function __construct(
        string $message,
        ?int $retryAfter = null,
        bool $isDaily = false,
    ) {
        parent::__construct($message, 429, 'rate_limit_error');
        $this->retryAfter = $retryAfter;
        $this->isDaily = $isDaily;
    }

    /**
     * Absolute Unix timestamp (seconds) when the limit resets, taken from the
     * X-RateLimit-Reset header. It is not a duration:
     * use max(0, $e->getRetryAfter() - time()) for the wait in seconds.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function isDaily(): bool
    {
        return $this->isDaily;
    }
}

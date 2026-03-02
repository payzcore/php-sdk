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

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function isDaily(): bool
    {
        return $this->isDaily;
    }
}

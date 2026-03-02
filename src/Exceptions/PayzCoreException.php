<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

use Exception;

class PayzCoreException extends Exception
{
    protected int $statusCode;
    protected string $errorCode;
    protected ?array $details;

    public function __construct(
        string $message,
        int $statusCode = 0,
        string $errorCode = 'api_error',
        ?array $details = null,
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<array{code: string, path?: string[], message: string}>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }
}

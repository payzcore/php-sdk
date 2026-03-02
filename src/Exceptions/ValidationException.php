<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

class ValidationException extends PayzCoreException
{
    public function __construct(string $message, ?array $details = null)
    {
        parent::__construct($message, 400, 'validation_error', $details);
    }
}

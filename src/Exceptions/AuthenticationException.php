<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

class AuthenticationException extends PayzCoreException
{
    public function __construct(string $message = 'Invalid or missing API key')
    {
        parent::__construct($message, 401, 'authentication_error');
    }
}

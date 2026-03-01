<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

class ForbiddenException extends PayzCoreException
{
    public function __construct(string $message = 'Access denied')
    {
        parent::__construct($message, 403, 'forbidden');
    }
}

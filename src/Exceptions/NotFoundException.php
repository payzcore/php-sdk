<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

class NotFoundException extends PayzCoreException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct($message, 404, 'not_found');
    }
}

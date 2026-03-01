<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

/**
 * Raised when external_order_id conflicts with a different external_ref (HTTP 409).
 */
class IdempotencyException extends PayzCoreException
{
    public function __construct(string $message = 'Idempotency conflict')
    {
        parent::__construct($message, 409, 'idempotency_error');
    }
}

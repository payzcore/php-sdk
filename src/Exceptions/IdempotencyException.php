<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

/**
 * Raised on any HTTP 409 Conflict: external_order_id reused with a different
 * external_ref or amount, cancelling a payment that is no longer pending, a
 * tx hash already used, etc. getMessage() has the exact reason.
 */
class IdempotencyException extends PayzCoreException
{
    public function __construct(string $message = 'Conflict')
    {
        parent::__construct($message, 409, 'idempotency_error');
    }
}

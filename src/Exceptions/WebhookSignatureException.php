<?php

declare(strict_types=1);

namespace PayzCore\Exceptions;

use Exception;

class WebhookSignatureException extends Exception
{
    public function __construct(string $message = 'Webhook signature verification failed')
    {
        parent::__construct($message);
    }
}

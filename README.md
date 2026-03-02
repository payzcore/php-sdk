# PayzCore PHP SDK

Official PHP SDK for the PayzCore blockchain transaction monitoring API.

## Important

**PayzCore is a blockchain monitoring service, not a payment processor.** All payments are sent directly to your own wallet addresses. PayzCore never holds, transfers, or has access to your funds.

- **Your wallets, your funds** — You provide your own wallet (HD xPub or static addresses). Customers pay directly to your addresses.
- **Read-only monitoring** — PayzCore watches the blockchain for incoming transactions and sends webhook notifications. That's it.
- **Protection Key security** — Sensitive operations like wallet management, address changes, and API key regeneration require a Protection Key that only you set. PayzCore cannot perform these actions without your authorization.
- **Your responsibility** — You are responsible for securing your own wallets and private keys. PayzCore provides monitoring and notification only.

## Requirements

- PHP 8.1+
- ext-curl
- ext-json
- ext-hash

## Installation

### Composer (recommended)

```bash
composer require payzcore/payzcore-php
```

### Manual

Download the `src/` directory and use PSR-4 autoloading, or require files manually.

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use PayzCore\PayzCore;

$payzcore = new PayzCore('pk_live_your_api_key');

// Create a payment monitoring request (network specified)
$result = $payzcore->payments->create([
    'amount' => 50,
    'externalRef' => 'user-123',
    'network' => PayzCore::NETWORK_TRC20,  // optional
    'metadata' => ['type' => 'topup'],
]);

echo $result['payment']['address']; // Deposit address
echo $result['payment']['amount'];  // Amount with random cents
echo $result['payment']['token'];   // 'USDT'

// Or let the customer choose the network on the payment page
$result = $payzcore->payments->create([
    'amount' => 50,
    'externalRef' => 'user-123',
]);

echo $result['payment']['awaitingNetwork'];   // true
echo $result['payment']['paymentUrl'];        // 'https://app.payzcore.com/pay/xxx'
print_r($result['payment']['availableNetworks']); // [{network, name, tokens}, ...]
```

## Supported Networks & Tokens

### Networks

| Constant | Value | Blockchain |
|----------|-------|------------|
| `PayzCore::NETWORK_TRC20` | `TRC20` | Tron |
| `PayzCore::NETWORK_BEP20` | `BEP20` | BNB Smart Chain |
| `PayzCore::NETWORK_ERC20` | `ERC20` | Ethereum |
| `PayzCore::NETWORK_POLYGON` | `POLYGON` | Polygon |
| `PayzCore::NETWORK_ARBITRUM` | `ARBITRUM` | Arbitrum |

All networks: `PayzCore::NETWORKS`

### Tokens

| Constant | Value |
|----------|-------|
| `PayzCore::TOKEN_USDT` | `USDT` |
| `PayzCore::TOKEN_USDC` | `USDC` |

All tokens: `PayzCore::TOKENS`

> **Note:** Token defaults to `USDT` when omitted, preserving backward compatibility.

## Usage

### Payments

```php
// Create payment (USDT on TRC20)
$result = $payzcore->payments->create([
    'amount' => 100,
    'externalRef' => 'order-456',
    'network' => PayzCore::NETWORK_TRC20,  // optional
]);

// Create payment with explicit token
$result = $payzcore->payments->create([
    'amount' => 100,
    'externalRef' => 'order-456',
    'network' => PayzCore::NETWORK_BEP20,
    'token' => PayzCore::TOKEN_USDC,     // optional, defaults to 'USDT'
    'externalOrderId' => 'INV-001',       // optional
    'expiresIn' => 3600,                  // optional, seconds (300-86400)
    'metadata' => ['plan' => 'pro'],      // optional
    'address' => 'Txxxx...',             // optional, static wallet dedicated mode only
]);

echo $result['payment']['network'];  // 'BEP20'
echo $result['payment']['token'];  // 'USDC'
// Static wallet projects may also return: $result['payment']['notice'],
// $result['payment']['original_amount'], $result['payment']['requires_txid']

// List payments
$result = $payzcore->payments->list([
    'status' => 'paid',  // optional: pending, confirming, partial, paid, overpaid, expired, cancelled
    'limit' => 20,       // optional
    'offset' => 0,       // optional
]);

foreach ($result['payments'] as $payment) {
    echo $payment['id'] . ': ' . $payment['status'] . ' (' . $payment['token'] . ")\n";
}

// Get payment details (latest cached status from database)
$result = $payzcore->payments->get('payment-uuid');
$payment = $result['payment'];

echo $payment['status'];
echo $payment['paidAmount'];
echo $payment['token'];  // 'USDT' or 'USDC'

foreach ($payment['transactions'] as $tx) {
    echo $tx['txHash'] . ': ' . $tx['amount'] . "\n";
}

// Cancel a pending payment
$result = $payzcore->payments->cancel('payment-uuid');
// $result['payment']['status'] === 'cancelled'

// Submit tx hash for verification (pool + txid mode)
$result = $payzcore->payments->confirm('payment-uuid', 'abc123def456...');
// $result['verified'], $result['status'], $result['amount_received']
```

### Projects (Master Key)

```php
use PayzCore\PayzCore;

$admin = new PayzCore('mk_your_master_key', ['masterKey' => true]);

// Create project
$result = $admin->projects->create([
    'name' => 'My Store',
    'slug' => 'my-store',
    'webhookUrl' => 'https://example.com/webhook',
]);

echo $result['project']['apiKey'];       // pk_live_xxx
echo $result['project']['webhookSecret']; // whsec_xxx

// List projects
$result = $admin->projects->list();
```

### Webhook Verification

```php
use PayzCore\Webhook;
use PayzCore\Exceptions\WebhookSignatureException;

$body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYZCORE_SIGNATURE'] ?? '';
$secret = 'whsec_your_webhook_secret';

try {
    $event = Webhook::constructEvent($body, $signature, $secret);

    switch ($event['event']) {
        case 'payment.completed':
            // Handle completed payment
            $paymentId = $event['paymentId'];
            $amount = $event['paidAmount'];
            $ref = $event['externalRef'];
            $network = $event['network'];   // e.g. 'TRC20'
            $token = $event['token'];   // e.g. 'USDT' or 'USDC'
            break;

        case 'payment.overpaid':
            // Handle overpayment
            break;

        case 'payment.expired':
            // Handle expiry
            break;

        case 'payment.partial':
            // Handle partial payment
            break;

        case 'payment.cancelled':
            // Handle cancellation
            break;
    }

    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (WebhookSignatureException $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
}
```

You can also verify the signature without parsing:

```php
$isValid = Webhook::verifySignature($body, $signature, $secret);
```

## Before Going Live

**Always test your setup before accepting real payments:**

1. **Verify your xPub** — In the PayzCore dashboard, click "Verify Key" when adding your wallet. Compare address #0 with your wallet app's first receiving address. They must match.
2. **Send a test payment** — Create a monitoring request for $1–5 and send the funds to the assigned address. Verify they arrive in your wallet.
3. **Test sweeping** — Send the test funds back out to confirm you control the derived addresses with your private keys.

> **Warning:** A wrong xPub key generates addresses you don't control. Funds sent to those addresses are permanently lost. PayzCore is watch-only and cannot recover funds. Please take 2 minutes to verify.

## Configuration

```php
$payzcore = new PayzCore('pk_live_xxx', [
    'baseUrl' => 'https://api.payzcore.com', // API base URL
    'timeout' => 30,                          // Request timeout in seconds
    'maxRetries' => 2,                        // Max retries on 5xx errors
    'masterKey' => false,                     // Use x-master-key header
]);
```

## Error Handling

```php
use PayzCore\Exceptions\PayzCoreException;
use PayzCore\Exceptions\AuthenticationException;
use PayzCore\Exceptions\ForbiddenException;
use PayzCore\Exceptions\NotFoundException;
use PayzCore\Exceptions\ValidationException;
use PayzCore\Exceptions\RateLimitException;
use PayzCore\Exceptions\IdempotencyException;

try {
    $result = $payzcore->payments->create([...]);
} catch (ValidationException $e) {
    // 400 - Invalid parameters
    echo $e->getMessage();
    print_r($e->getDetails()); // Validation error details
} catch (AuthenticationException $e) {
    // 401 - Invalid API key
} catch (ForbiddenException $e) {
    // 403 - Access denied
} catch (NotFoundException $e) {
    // 404 - Resource not found
} catch (RateLimitException $e) {
    // 429 - Rate limited
    echo $e->getRetryAfter(); // Seconds until reset (or null)
    echo $e->isDaily();       // Whether this is a daily limit
} catch (IdempotencyException $e) {
    // 409 - external_order_id reused with different external_ref
} catch (PayzCoreException $e) {
    // Other API errors (5xx, etc.)
    echo $e->getStatusCode();
    echo $e->getErrorCode();
}
```

## Static Wallet Mode

When the PayzCore project is configured with a static wallet, the API works the same way but may return additional fields in the response:

| Field | Type | Description |
|-------|------|-------------|
| `notice` | `string` | Instructions for the payer (e.g. "Send exact amount") |
| `original_amount` | `string` | The original requested amount before any adjustments |
| `requires_txid` | `bool` | Whether the payer must submit their transaction ID |

In dedicated address mode, you can specify which static address to assign to a customer using the `address` parameter on payment creation. In shared address mode, the project's single static address is used automatically.

> **Note:** The `address` parameter is only used with static wallet projects in dedicated mode. For HD wallet projects, this parameter is ignored.

## Token Parameter

The `token` parameter is optional. If omitted in `create()`, the API defaults to `USDT`. Response and webhook payloads always include a `token` field.

## See Also

- [Getting Started](https://docs.payzcore.com/getting-started) — Account setup and first payment
- [Authentication & API Keys](https://docs.payzcore.com/guides/authentication) — API key management
- [Webhooks Guide](https://docs.payzcore.com/guides/webhooks) — Events, headers, and signature verification
- [Supported Networks](https://docs.payzcore.com/guides/networks) — Available networks and tokens
- [Error Reference](https://docs.payzcore.com/guides/errors) — HTTP status codes and troubleshooting
- [API Reference](https://docs.payzcore.com) — Interactive API documentation (Scalar UI)

## License

MIT

<?php

declare(strict_types=1);

namespace PayzCore;

use PayzCore\Exceptions\WebhookSignatureException;

class Webhook
{
    /** Supported blockchain networks. */
    public const SUPPORTED_NETWORKS = ['TRC20', 'BEP20', 'ERC20', 'POLYGON', 'ARBITRUM'];

    /** Supported stablecoin tokens. */
    public const SUPPORTED_TOKENS = ['USDT', 'USDC'];

    /**
     * Verify a webhook signature from PayzCore.
     *
     * @param string $body Raw request body string
     * @param string $signature Value of X-PayzCore-Signature header
     * @param string $secret Webhook secret from project creation (whsec_xxx)
     * @param string|null $timestamp Value of X-PayzCore-Timestamp header (optional)
     * @param int $toleranceSeconds Max age in seconds (default: 300 = 5 minutes)
     * @return bool true if signature is valid
     */
    public static function verifySignature(
        string $body,
        string $signature,
        string $secret,
        ?string $timestamp = null,
        int $toleranceSeconds = 300
    ): bool {
        if ($body === '' || $signature === '' || $secret === '') {
            return false;
        }

        // Timestamp replay protection (optional but recommended)
        if ($timestamp !== null) {
            $ts = strtotime($timestamp);
            if ($ts === false || abs(time() - $ts) > $toleranceSeconds) {
                return false;
            }
        }

        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Parse a raw webhook body into a structured array.
     * Triggers warnings for unknown network/token values (forward-compatible).
     *
     * @param string $body Raw request body string (JSON)
     * @return array{
     *     event: string,
     *     paymentId: string,
     *     externalRef: string,
     *     externalOrderId: ?string,
     *     network: string,
     *     token: string,
     *     address: string,
     *     expectedAmount: string,
     *     paidAmount: string,
     *     txHash: ?string,
     *     status: string,
     *     paidAt: ?string,
     *     metadata: array<string, mixed>,
     *     timestamp: string,
     *     buyerEmail: ?string,
     *     buyerName: ?string,
     *     buyerNote: ?string,
     *     paymentLinkId: ?string,
     *     paymentLinkSlug: ?string
     * }
     * @throws WebhookSignatureException
     */
    public static function parseWebhook(string $body): array
    {
        $raw = json_decode($body, true);

        if (!is_array($raw)) {
            throw new WebhookSignatureException('Invalid webhook payload: malformed JSON');
        }

        $network = $raw['network'] ?? '';
        if ($network !== '' && !in_array($network, self::SUPPORTED_NETWORKS, true)) {
            trigger_error("[PayzCore] Unknown network in webhook: {$network}", E_USER_WARNING);
        }

        $token = $raw['token'] ?? '';
        if ($token !== '' && !in_array($token, self::SUPPORTED_TOKENS, true)) {
            trigger_error("[PayzCore] Unknown token in webhook: {$token}", E_USER_WARNING);
        }

        $required = ['event', 'payment_id', 'external_ref', 'network', 'address',
                     'expected_amount', 'paid_amount', 'status', 'timestamp'];
        foreach ($required as $field) {
            if (!isset($raw[$field])) {
                throw new WebhookSignatureException("Invalid webhook payload: missing field '{$field}'");
            }
        }

        $result = [
            'event' => $raw['event'],
            'paymentId' => $raw['payment_id'],
            'externalRef' => $raw['external_ref'],
            'externalOrderId' => $raw['external_order_id'] ?? null,
            'network' => $raw['network'],
            'token' => $raw['token'] ?? 'USDT',
            'address' => $raw['address'],
            'expectedAmount' => $raw['expected_amount'],
            'paidAmount' => $raw['paid_amount'],
            'txHash' => $raw['tx_hash'] ?? null,
            'status' => $raw['status'],
            /** Only set for payment.completed and payment.overpaid events; null for others. */
            'paidAt' => $raw['paid_at'] ?? null,
            'metadata' => $raw['metadata'] ?? [],
            'timestamp' => $raw['timestamp'],
        ];

        // Payment link buyer fields (only present for payment link payments)
        if (isset($raw['buyer_email'])) {
            $result['buyerEmail'] = $raw['buyer_email'];
        }
        if (isset($raw['buyer_name'])) {
            $result['buyerName'] = $raw['buyer_name'];
        }
        if (isset($raw['buyer_note'])) {
            $result['buyerNote'] = $raw['buyer_note'];
        }
        if (isset($raw['payment_link_id'])) {
            $result['paymentLinkId'] = $raw['payment_link_id'];
        }
        if (isset($raw['payment_link_slug'])) {
            $result['paymentLinkSlug'] = $raw['payment_link_slug'];
        }

        return $result;
    }

    /**
     * Verify signature and parse the webhook payload.
     * Throws WebhookSignatureException if signature is invalid.
     *
     * @param string $body Raw request body string
     * @param string $signature Value of X-PayzCore-Signature header
     * @param string $secret Webhook secret from project creation (whsec_xxx)
     * @param string|null $timestamp Value of X-PayzCore-Timestamp header (optional)
     * @param int $toleranceSeconds Max age in seconds (default: 300 = 5 minutes)
     * @return array{
     *     event: string,
     *     paymentId: string,
     *     externalRef: string,
     *     externalOrderId: ?string,
     *     network: string,
     *     token: string,
     *     address: string,
     *     expectedAmount: string,
     *     paidAmount: string,
     *     txHash: ?string,
     *     status: string,
     *     paidAt: ?string,
     *     metadata: array<string, mixed>,
     *     timestamp: string,
     *     buyerEmail: ?string,
     *     buyerName: ?string,
     *     buyerNote: ?string,
     *     paymentLinkId: ?string,
     *     paymentLinkSlug: ?string
     * }
     * @throws WebhookSignatureException
     */
    public static function constructEvent(
        string $body,
        string $signature,
        string $secret,
        ?string $timestamp = null,
        int $toleranceSeconds = 300
    ): array {
        if (empty($body) || empty($signature) || empty($secret)) {
            throw new WebhookSignatureException('Missing required parameters');
        }

        if (!self::verifySignature($body, $signature, $secret, $timestamp, $toleranceSeconds)) {
            throw new WebhookSignatureException();
        }

        return self::parseWebhook($body);
    }
}

<?php

declare(strict_types=1);

namespace PayzCore\Resources;

use PayzCore\HttpClient;

class Payments
{
    private HttpClient $client;

    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a payment monitoring request.
     *
     * @param array{
     *     amount: int|float,
     *     externalRef: string,
     *     network?: string,
     *     token?: string,
     *     externalOrderId?: string,
     *     address?: string,
     *     expiresIn?: int,
     *     metadata?: array<string, mixed>
     * } $params
     * @return array{
     *     success: true,
     *     existing: bool,
     *     payment: array{
     *         id: string,
     *         address: ?string,
     *         amount: string,
     *         network: ?string,
     *         token: ?string,
     *         status: string,
     *         expiresAt: string,
     *         externalOrderId: ?string,
     *         qrCode: ?string,
     *         awaitingNetwork: ?bool,
     *         paymentUrl: ?string,
     *         availableNetworks: ?array
     *     }
     * }
     */
    public function create(array $params): array
    {
        $body = [
            'amount' => $params['amount'],
            'external_ref' => $params['externalRef'],
        ];

        if (isset($params['network'])) {
            $body['network'] = $params['network'];
        }
        if (isset($params['token'])) {
            $body['token'] = $params['token'];
        }
        if (isset($params['externalOrderId'])) {
            $body['external_order_id'] = $params['externalOrderId'];
        }
        if (isset($params['address'])) {
            $body['address'] = $params['address'];
        }
        if (isset($params['expiresIn'])) {
            $body['expires_in'] = $params['expiresIn'];
        }
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }

        $raw = $this->client->post('/v1/payments', $body);
        $payment = $raw['payment'];

        $result = [
            'success' => true,
            'existing' => (bool)$raw['existing'],
            'payment' => [
                'id' => $payment['id'],
                'address' => $payment['address'] ?? null,
                'amount' => $payment['amount'],
                'network' => $payment['network'] ?? null,
                'token' => $payment['token'] ?? null,
                'status' => $payment['status'],
                'expiresAt' => $payment['expires_at'],
                'externalOrderId' => $payment['external_order_id'] ?? null,
                'qrCode' => $payment['qr_code'] ?? null,
                'notice' => $payment['notice'] ?? null,
                'originalAmount' => $payment['original_amount'] ?? null,
                'requiresTxid' => $payment['requires_txid'] ?? false,
                'confirmEndpoint' => $payment['confirm_endpoint'] ?? null,
                'awaitingNetwork' => $payment['awaiting_network'] ?? null,
                'paymentUrl' => $payment['payment_url'] ?? null,
                'availableNetworks' => $payment['available_networks'] ?? null,
            ],
        ];

        return $result;
    }

    /**
     * List payments.
     *
     * @param array{status?: string, limit?: int, offset?: int} $params
     * @return array{
     *     success: true,
     *     payments: array<array{
     *         id: string,
     *         externalRef: string,
     *         externalOrderId: ?string,
     *         network: ?string,
     *         token: ?string,
     *         address: ?string,
     *         expectedAmount: string,
     *         paidAmount: string,
     *         status: string,
     *         txHash: ?string,
     *         expiresAt: string,
     *         paidAt: ?string,
     *         createdAt: string
     *     }>
     * }
     */
    public function list(array $params = []): array
    {
        $query = [];
        if (isset($params['status'])) {
            $query['status'] = $params['status'];
        }
        if (isset($params['limit'])) {
            $query['limit'] = (string)$params['limit'];
        }
        if (isset($params['offset'])) {
            $query['offset'] = (string)$params['offset'];
        }

        $qs = http_build_query($query);
        $path = '/v1/payments' . ($qs !== '' ? '?' . $qs : '');
        $raw = $this->client->get($path);

        $payments = array_map(fn(array $p): array => [
            'id' => $p['id'],
            'externalRef' => $p['external_ref'],
            'externalOrderId' => $p['external_order_id'] ?? null,
            'network' => $p['network'] ?? null,
            'token' => $p['token'] ?? null,
            'address' => $p['address'] ?? null,
            'expectedAmount' => $p['expected_amount'],
            'paidAmount' => $p['paid_amount'],
            'status' => $p['status'],
            'txHash' => $p['tx_hash'] ?? null,
            'expiresAt' => $p['expires_at'],
            'paidAt' => $p['paid_at'] ?? null,
            'createdAt' => $p['created_at'],
        ], $raw['payments']);

        return [
            'success' => true,
            'payments' => $payments,
        ];
    }

    /**
     * Get payment details (latest cached status from database).
     *
     * @return array{
     *     success: true,
     *     payment: array{
     *         id: string,
     *         status: string,
     *         expectedAmount: string,
     *         paidAmount: string,
     *         address: ?string,
     *         network: ?string,
     *         token: ?string,
     *         txHash: ?string,
     *         expiresAt: string,
     *         awaitingNetwork: ?bool,
     *         transactions: array<array{txHash: string, amount: string, from: string, confirmed: bool, confirmations: int}>
     *     }
     * }
     */
    public function get(string $id): array
    {
        $raw = $this->client->get('/v1/payments/' . urlencode($id));
        $payment = $raw['payment'];

        $transactions = array_map(fn(array $t): array => [
            'txHash' => $t['tx_hash'],
            'amount' => $t['amount'],
            'from' => $t['from'],
            'confirmed' => (bool)$t['confirmed'],
            'confirmations' => (int)($t['confirmations'] ?? 0),
        ], $payment['transactions'] ?? []);

        return [
            'success' => true,
            'payment' => [
                'id' => $payment['id'],
                'status' => $payment['status'],
                'expectedAmount' => $payment['expected_amount'],
                'paidAmount' => $payment['paid_amount'],
                'address' => $payment['address'] ?? null,
                'network' => $payment['network'] ?? null,
                'token' => $payment['token'] ?? null,
                'txHash' => $payment['tx_hash'] ?? null,
                'expiresAt' => $payment['expires_at'],
                'awaitingNetwork' => $payment['awaiting_network'] ?? null,
                'transactions' => $transactions,
            ],
        ];
    }

    /**
     * Cancel a pending payment.
     *
     * @return array{success: true, payment: array{id: string, status: string, expectedAmount: string, address: ?string, network: ?string, token: ?string, expiresAt: string}}
     */
    public function cancel(string $id): array
    {
        $raw = $this->client->patch('/v1/payments/' . urlencode($id), [
            'status' => 'cancelled',
        ]);
        $payment = $raw['payment'];

        return [
            'success' => true,
            'payment' => [
                'id' => $payment['id'],
                'status' => $payment['status'],
                'expectedAmount' => $payment['expected_amount'],
                'address' => $payment['address'] ?? null,
                'network' => $payment['network'] ?? null,
                'token' => $payment['token'] ?? null,
                'expiresAt' => $payment['expires_at'],
            ],
        ];
    }

    /**
     * Submit a transaction hash for verification (pool + txid mode only).
     *
     * @param string $id Payment ID.
     * @param string $txHash Blockchain transaction hash (hex digits only, no 0x prefix).
     * @return array{success: true, status: string, verified: bool, amount_received?: string, amount_expected?: string, message?: string}
     */
    public function confirm(string $id, string $txHash): array
    {
        $raw = $this->client->post('/v1/payments/' . urlencode($id) . '/confirm', [
            'tx_hash' => $txHash,
        ]);

        $result = [
            'success' => true,
            'status' => $raw['status'],
            'verified' => (bool)$raw['verified'],
        ];

        if (isset($raw['amount_received'])) {
            $result['amount_received'] = $raw['amount_received'];
        }
        if (isset($raw['amount_expected'])) {
            $result['amount_expected'] = $raw['amount_expected'];
        }
        if (isset($raw['message'])) {
            $result['message'] = $raw['message'];
        }

        return $result;
    }
}

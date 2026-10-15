<?php
// Router for `php -S` used by run.php. Returns canned API responses.
declare(strict_types=1);

header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$payment = [
    'id' => 'p1', 'address' => 'Txxx', 'amount' => '50.07', 'network' => 'TRC20',
    'token' => 'USDT', 'status' => 'pending', 'expires_at' => '2026-10-15T12:00:00Z',
    'external_order_id' => null, 'qr_code' => 'data:image/png;base64,xx',
    'payment_url' => 'https://pay.payzcore.com/p1',
];

if ($method === 'POST' && $path === '/v1/payments') {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (($body['external_ref'] ?? '') === 'fail') {
        // count attempts so run.php can check POST is not retried
        file_put_contents(sys_get_temp_dir() . '/payzcore-sdk-test-attempts', '1', FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create payment']);
        return;
    }
    // A brand-new payment: no "existing" key (as the real API does)
    http_response_code(201);
    echo json_encode(['success' => true, 'payment' => $payment]);
    return;
}
if ($method === 'GET' && $path === '/v1/payments') {
    echo json_encode([
        'success' => true,
        'payments' => [[
            'id' => 'p1', 'external_ref' => 'u1', 'external_order_id' => null,
            'network' => 'TRC20', 'token' => 'USDT', 'address' => 'Txxx',
            'expected_amount' => '50.07', 'paid_amount' => '0', 'status' => 'pending',
            'tx_hash' => null, 'expires_at' => '2026-10-15T12:00:00Z', 'paid_at' => null,
            'created_at' => '2026-10-15T11:00:00Z', 'updated_at' => '2026-10-15T11:05:00Z',
        ]],
        'pagination' => ['total' => 1, 'limit' => 50, 'offset' => 0, 'has_more' => false],
        'query' => $_GET,
    ]);
    return;
}
if ($method === 'GET' && $path === '/v1/projects') {
    echo json_encode([
        'success' => true, 'total' => 1, 'limit' => 100, 'offset' => 0,
        'projects' => [[
            'id' => 'pr1', 'name' => 'Store', 'slug' => 'store', 'api_key_prefix' => 'pk_live_ab',
            'webhook_url' => null, 'is_active' => true, 'created_at' => '2026-10-15T11:00:00Z',
        ]],
    ]);
    return;
}
http_response_code(404);
echo json_encode(['error' => 'Not found']);

<?php
// Regression tests without PHPUnit: `php tests/run.php` (needs ext-curl).
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'PayzCore\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use PayzCore\PayzCore;
use PayzCore\Webhook;

// Turn every warning/notice into an exception, like Laravel/Symfony do.
set_error_handler(function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false; // silenced with @
    }
    throw new ErrorException($msg, 0, $no, $file, $line);
});

$port = 18000 + random_int(0, 999);
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/mock-server.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
);
usleep(400000);
$attemptsFile = sys_get_temp_dir() . '/payzcore-sdk-test-attempts';
@unlink($attemptsFile);

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

try {
    $client = new PayzCore('pk_live_test', ['baseUrl' => "http://127.0.0.1:$port", 'maxRetries' => 2]);

    $res = $client->payments->create(['amount' => 50, 'externalRef' => 'u1', 'network' => 'TRC20']);
    check('create() without "existing" key returns existing=false', $res['existing'] === false);
    check('create() maps payment id', $res['payment']['id'] === 'p1');

    try {
        $client->payments->create(['amount' => 50, 'externalRef' => 'fail']);
        check('create() 500 throws', false);
    } catch (\PayzCore\Exceptions\PayzCoreException $e) {
        check('create() 500 throws', $e->getStatusCode() === 500);
    }
    check('POST without externalOrderId is not retried', strlen((string)@file_get_contents($attemptsFile)) === 1);

    $list = $client->payments->list(['updatedAfter' => '2026-10-15T00:00:00Z']);
    check('list() maps updatedAt', $list['payments'][0]['updatedAt'] === '2026-10-15T11:05:00Z');
    check('list() returns pagination', $list['pagination']['total'] === 1 && $list['pagination']['hasMore'] === false);

    $admin = new PayzCore('mk_test', ['baseUrl' => "http://127.0.0.1:$port", 'masterKey' => true]);
    $projects = $admin->projects->list();
    check('projects->list() maps apiKeyPrefix', $projects['projects'][0]['apiKeyPrefix'] === 'pk_live_ab');

    $event = Webhook::parseWebhook((string)json_encode([
        'event' => 'payment.cancelled', 'payment_id' => 'p1', 'external_ref' => 'u1',
        'network' => null, 'token' => null, 'address' => null,
        'expected_amount' => '50.00', 'paid_amount' => '0', 'tx_hash' => null,
        'status' => 'cancelled', 'paid_at' => null, 'metadata' => [],
        'timestamp' => '2026-10-15T11:00:00.000Z',
    ]));
    check('parseWebhook() accepts null network/address', $event['network'] === null && $event['address'] === null);
} catch (\Throwable $e) {
    check('unexpected ' . get_class($e) . ': ' . $e->getMessage(), false);
} finally {
    proc_terminate($server);
    @unlink($attemptsFile);
}

echo $failures === 0 ? "\nAll tests passed\n" : "\n$failures test(s) failed\n";
exit($failures === 0 ? 0 : 1);

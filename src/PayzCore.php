<?php

declare(strict_types=1);

namespace PayzCore;

use InvalidArgumentException;
use PayzCore\Resources\Payments;
use PayzCore\Resources\Projects;

class PayzCore
{
    // Supported blockchain networks
    public const NETWORK_TRC20 = 'TRC20';
    public const NETWORK_BEP20 = 'BEP20';
    public const NETWORK_ERC20 = 'ERC20';
    public const NETWORK_POLYGON = 'POLYGON';
    public const NETWORK_ARBITRUM = 'ARBITRUM';

    /** @var string[] All supported networks */
    public const NETWORKS = [
        self::NETWORK_TRC20,
        self::NETWORK_BEP20,
        self::NETWORK_ERC20,
        self::NETWORK_POLYGON,
        self::NETWORK_ARBITRUM,
    ];

    // Supported tokens
    public const TOKEN_USDT = 'USDT';
    public const TOKEN_USDC = 'USDC';

    /** @var string[] All supported tokens */
    public const TOKENS = [
        self::TOKEN_USDT,
        self::TOKEN_USDC,
    ];

    public readonly Payments $payments;
    public readonly Projects $projects;

    /**
     * Create a new PayzCore SDK instance.
     *
     * @param string $apiKey API key (pk_live_xxx) or master key (mk_xxx)
     * @param array{baseUrl?: string, timeout?: int, maxRetries?: int, masterKey?: bool} $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'PayzCore API key is required. Pass your pk_live_xxx or mk_xxx key.'
            );
        }

        $client = new HttpClient($apiKey, $options);
        $this->payments = new Payments($client);
        $this->projects = new Projects($client);
    }
}

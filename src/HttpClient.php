<?php

declare(strict_types=1);

namespace PayzCore;

use PayzCore\Exceptions\AuthenticationException;
use PayzCore\Exceptions\ForbiddenException;
use PayzCore\Exceptions\IdempotencyException;
use PayzCore\Exceptions\NotFoundException;
use PayzCore\Exceptions\PayzCoreException;
use PayzCore\Exceptions\RateLimitException;
use PayzCore\Exceptions\ValidationException;

class HttpClient
{
    private const DEFAULT_BASE_URL = 'https://api.payzcore.com';
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_MAX_RETRIES = 2;
    private const RETRY_BASE_MS = 200;
    private const SDK_VERSION = '1.0.0';

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $maxRetries;
    private bool $useMasterKey;

    /**
     * @param string $apiKey API key (pk_live_xxx) or master key (mk_xxx)
     * @param array{baseUrl?: string, timeout?: int, maxRetries?: int, masterKey?: bool} $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['baseUrl'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $this->maxRetries = $options['maxRetries'] ?? self::DEFAULT_MAX_RETRIES;
        $this->useMasterKey = $options['masterKey'] ?? false;
    }

    /**
     * @return array<string, mixed>
     * @throws PayzCoreException
     */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws PayzCoreException
     */
    public function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws PayzCoreException
     */
    public function patch(string $path, array $body): array
    {
        return $this->request('PATCH', $path, $body);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     * @throws PayzCoreException
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;

        $headers = [
            'User-Agent: payzcore-php/' . self::SDK_VERSION,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        if ($this->useMasterKey) {
            $headers[] = 'x-master-key: ' . $this->apiKey;
        } else {
            $headers[] = 'x-api-key: ' . $this->apiKey;
        }

        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            if ($attempt > 0) {
                $delayMs = self::RETRY_BASE_MS * (2 ** ($attempt - 1));
                usleep($delayMs * 1000);
            }

            $responseHeaders = [];
            $ch = curl_init();

            try {
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_HEADERFUNCTION => function ($ch, string $header) use (&$responseHeaders): int {
                        $len = strlen($header);
                        $parts = explode(':', $header, 2);
                        if (count($parts) === 2) {
                            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                        }
                        return $len;
                    },
                ]);

                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    if ($body !== null) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
                    }
                } elseif ($method === 'PATCH') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    if ($body !== null) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
                    }
                }

                $responseBody = curl_exec($ch);

                if ($responseBody === false) {
                    $lastException = new PayzCoreException(
                        'Network error: ' . curl_error($ch),
                        0,
                        'network_error',
                    );
                    continue;
                }

                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $decoded = json_decode((string)$responseBody, true);
                    if (!is_array($decoded)) {
                        throw new PayzCoreException('Invalid JSON response', $statusCode, 'parse_error');
                    }
                    return $decoded;
                }

                // Non-retryable client errors (except 429)
                if ($statusCode < 500 && $statusCode !== 429) {
                    throw $this->buildApiError($statusCode, (string)$responseBody, $responseHeaders);
                }

                // 429 - don't retry
                if ($statusCode === 429) {
                    throw $this->buildApiError($statusCode, (string)$responseBody, $responseHeaders);
                }

                // 5xx - retry if attempts remain
                $lastException = $this->buildApiError($statusCode, (string)$responseBody, $responseHeaders);
            } finally {
                curl_close($ch);
            }
        }

        throw $lastException ?? new PayzCoreException('Request failed after retries', 0, 'network_error');
    }

    /**
     * @param array<string, string> $headers
     */
    private function buildApiError(int $statusCode, string $responseBody, array $headers): PayzCoreException
    {
        $body = json_decode($responseBody, true);
        $message = $body['error'] ?? 'Unknown error';
        $details = $body['details'] ?? null;

        return match ($statusCode) {
            400 => new ValidationException($message, $details),
            401 => new AuthenticationException($message),
            403 => new ForbiddenException($message),
            404 => new NotFoundException($message),
            409 => new IdempotencyException($message),
            429 => new RateLimitException(
                $message,
                isset($headers['x-ratelimit-reset']) ? (int)$headers['x-ratelimit-reset'] : null,
                ($headers['x-ratelimit-daily'] ?? '') === 'true',
            ),
            default => new PayzCoreException($message, $statusCode, 'api_error'),
        };
    }
}

<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

use CurlHandle;
use RuntimeException;

/** POSTs GraphQL requests over one kept-alive connection, as a browser tab would. */
final class CurlTransport
{
    private readonly CurlHandle $curl;

    public function __construct(string $url, int $timeoutSeconds = 180)
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException("cannot open $url");
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);
        $this->curl = $curl;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{status: int, json: mixed}
     */
    public function __invoke(array $body): array
    {
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        $response = curl_exec($this->curl);
        $status = (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        if (!is_string($response)) {
            return ['status' => $status, 'json' => null];
        }

        return ['status' => $status, 'json' => json_decode($response, true)];
    }
}

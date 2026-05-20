<?php

declare(strict_types=1);

namespace Propit\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Propit\Config\PropitConfig;
use Propit\Contracts\PropitAuthenticatorInterface;
use Propit\Contracts\PropitHttpClientInterface;
use Propit\DTO\HttpResponse;
use Propit\Exceptions\ApiException;
use Propit\Exceptions\AuthException;
use Propit\Exceptions\PublisherNotReadyException;
use Propit\Exceptions\RateLimitException;
use Propit\Support\StructuredLogger;

final class GuzzlePropitHttpClient implements PropitHttpClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly PropitConfig $config,
        private readonly PropitAuthenticatorInterface $authenticator,
        private readonly StructuredLogger $logger,
    ) {
    }

    public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            $start = microtime(true);

            $finalHeaders = $this->authenticator->authenticate(array_merge([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => $this->config->userAgent(),
            ], $this->config->customHeaders(), $headers));

            try {
                $fullUri = rtrim($this->config->baseUrl(), '/') . '/' . ltrim($uri, '/');
                $resp = $this->client->request($method, $fullUri, [
                    'headers' => $finalHeaders,
                    'query' => $query,
                    'json' => $json,
                    'timeout' => $this->config->timeout(),
                    'http_errors' => false,
                ]);
            } catch (GuzzleException $e) {
                throw new ApiException('Transport error calling Proppit.', 0, $method, $uri, null, ['error' => $e->getMessage()], $e);
            }

            $response = $this->mapResponse($resp);
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $requestId = $response->headers['x-request-id'][0] ?? $response->json['requestId'] ?? null;

            $this->logger->info('propit_http', [
                'method' => $method,
                'uri' => $uri,
                'status_code' => $response->statusCode,
                'duration_ms' => $durationMs,
                'attempt' => $attempt,
                'request_id' => $requestId,
                'error_code' => $response->json['error'] ?? null,
                'headers' => $finalHeaders,
            ]);

            if ($response->successful()) {
                return $response;
            }

            if (($response->statusCode === 429 || $response->serverError()) && $attempt <= $this->config->retryAttempts() + 1) {
                usleep($this->backoffMicros($attempt, $response));
                continue;
            }

            throw $this->toException($method, $uri, $response);
        }
    }

    private function mapResponse(ResponseInterface $resp): HttpResponse
    {
        $raw = (string) $resp->getBody();
        $decoded = json_decode($raw, true);

        return new HttpResponse(
            statusCode: $resp->getStatusCode(),
            headers: $resp->getHeaders(),
            rawBody: $raw,
            json: is_array($decoded) ? $decoded : [],
        );
    }

    private function backoffMicros(int $attempt, HttpResponse $response): int
    {
        $retryAfter = isset($response->headers['Retry-After'][0]) ? (int) $response->headers['Retry-After'][0] : null;
        if ($retryAfter !== null && $retryAfter > 0) {
            return $retryAfter * 1_000_000;
        }

        $base = max(1, $this->config->retryDelayMs()) * (2 ** max(0, $attempt - 1));
        $jitter = random_int(0, 100);

        return (int) (($base + $jitter) * 1000);
    }

    private function toException(string $method, string $uri, HttpResponse $response): \Throwable
    {
        $ctx = [
            'status' => $response->statusCode,
            'body' => StructuredLogger::sanitize($response->json),
            'headers' => StructuredLogger::sanitize($response->headers),
        ];

        if ($response->statusCode === 403) {
            $apiError  = $response->json['error'] ?? $response->json['message'] ?? '';
            $requestId = isset($response->json['requestId']) ? (string) $response->json['requestId'] : null;

            if (is_string($apiError) && stripos($apiError, 'publisher could not publish') !== false) {
                return new PublisherNotReadyException('', $requestId, $ctx);
            }

            $detail = is_string($apiError) && $apiError !== '' ? ": {$apiError}" : '';
            return new AuthException('Unauthorized response from Proppit' . $detail . '.', $ctx, 403);
        }

        if ($response->statusCode === 401) {
            $apiError = $response->json['error'] ?? $response->json['message'] ?? $response->rawBody;
            $detail   = is_string($apiError) && $apiError !== '' ? ": {$apiError}" : '';
            return new AuthException('Unauthorized response from Proppit' . $detail . '.', $ctx, 401);
        }

        if ($response->statusCode === 429) {
            $retryAfter = isset($response->headers['Retry-After'][0]) ? (int) $response->headers['Retry-After'][0] : null;

            return new RateLimitException('Proppit rate limit reached.', 429, $method, $uri, $retryAfter, $ctx);
        }

        return new ApiException(
            message: (string) ($response->json['error'] ?? 'Proppit API request failed.'),
            statusCode: $response->statusCode,
            method: $method,
            endpoint: $uri,
            proppitErrorCode: isset($response->json['error']) ? (string) $response->json['error'] : null,
            context: $ctx,
        );
    }
}

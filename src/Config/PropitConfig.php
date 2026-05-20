<?php

declare(strict_types=1);

namespace Propit\Config;

use Propit\Exceptions\AuthException;
use InvalidArgumentException;

final class PropitConfig
{
    private function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private int $timeout,
        private int $retryAttempts,
        private int $retryDelayMs,
        private string $userAgent,
        private bool $enableStructuredLogs,
        private string $country,
        private ?string $publisherExternalId,
        private array $customHeaders,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid PROPIT_BASE_URL.');
        }
        if ($this->clientId === '') {
            throw new AuthException('Missing PROPIT_CLIENT_ID. Set it in your .env file.');
        }
        if ($this->clientSecret === '') {
            throw new AuthException('Missing PROPIT_CLIENT_SECRET. Set it in your .env file.');
        }
        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('PROPIT_TIMEOUT must be > 0.');
        }
        if ($this->retryAttempts < 0) {
            throw new InvalidArgumentException('PROPIT_RETRY_ATTEMPTS must be >= 0.');
        }
        if ($this->retryDelayMs < 0) {
            throw new InvalidArgumentException('PROPIT_RETRY_DELAY_MS must be >= 0.');
        }
    }

    public static function fromArray(array $config): self
    {
        // Accept client_id/client_secret (current) or api_user/api_password (legacy fallback)
        $clientId     = (string) ($config['client_id']     ?? $config['api_user']     ?? $config['api_key']    ?? '');
        $clientSecret = (string) ($config['client_secret'] ?? $config['api_password'] ?? $config['api_secret'] ?? '');

        return new self(
            baseUrl: (string) ($config['base_url'] ?? 'https://real-time.proppit.com/api/v2'),
            clientId: $clientId,
            clientSecret: $clientSecret,
            timeout: (int) ($config['timeout'] ?? 30),
            retryAttempts: (int) ($config['retry_attempts'] ?? 3),
            retryDelayMs: (int) ($config['retry_delay_ms'] ?? 500),
            userAgent: (string) ($config['user_agent'] ?? 'Homlity-Proppit-SDK/1.0'),
            enableStructuredLogs: (bool) ($config['enable_structured_logs'] ?? true),
            country: strtoupper((string) ($config['country'] ?? 'CO')),
            publisherExternalId: isset($config['publisher_external_id']) ? (string) $config['publisher_external_id'] : null,
            customHeaders: (array) ($config['custom_headers'] ?? []),
        );
    }

    public function baseUrl(): string   { return $this->baseUrl; }
    public function clientId(): string  { return $this->clientId; }
    public function clientSecret(): string { return $this->clientSecret; }
    public function timeout(): int      { return $this->timeout; }
    public function retryAttempts(): int { return $this->retryAttempts; }
    public function retryDelayMs(): int { return $this->retryDelayMs; }
    public function userAgent(): string { return $this->userAgent; }
    public function structuredLogsEnabled(): bool { return $this->enableStructuredLogs; }
    public function country(): string   { return $this->country; }
    public function publisherExternalId(): ?string { return $this->publisherExternalId; }
    public function customHeaders(): array { return $this->customHeaders; }

    /** @deprecated Use clientId() */
    public function apiUser(): string { return $this->clientId; }

    /** @deprecated Use clientSecret() */
    public function apiPassword(): string { return $this->clientSecret; }

    public function redacted(): array
    {
        return [
            'base_url'       => $this->baseUrl,
            'client_id'      => $this->clientId !== '' ? substr($this->clientId, 0, 4) . '***' : '',
            'client_secret'  => '***',
            'timeout'        => $this->timeout,
            'retry_attempts' => $this->retryAttempts,
            'retry_delay_ms' => $this->retryDelayMs,
            'user_agent'     => $this->userAgent,
            'country'        => $this->country,
        ];
    }
}

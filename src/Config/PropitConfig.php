<?php

declare(strict_types=1);

namespace Propit\Config;

use InvalidArgumentException;

final class PropitConfig
{
    private function __construct(
        private string $baseUrl,
        private string $apiUser,
        private string $apiPassword,
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
        if ($this->apiUser === '' || $this->apiPassword === '') {
            throw new InvalidArgumentException('Missing Proppit credentials (user/password).');
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
        return new self(
            baseUrl: (string) ($config['base_url'] ?? 'https://real-time.proppit.com/api/v2'),
            apiUser: (string) ($config['api_user'] ?? $config['api_key'] ?? ''),
            apiPassword: (string) ($config['api_password'] ?? $config['api_secret'] ?? ''),
            timeout: (int) ($config['timeout'] ?? 15),
            retryAttempts: (int) ($config['retry_attempts'] ?? 2),
            retryDelayMs: (int) ($config['retry_delay_ms'] ?? 250),
            userAgent: (string) ($config['user_agent'] ?? 'PropitSDK/1.0'),
            enableStructuredLogs: (bool) ($config['enable_structured_logs'] ?? false),
            country: strtoupper((string) ($config['country'] ?? 'CO')),
            publisherExternalId: isset($config['publisher_external_id']) ? (string) $config['publisher_external_id'] : null,
            customHeaders: (array) ($config['custom_headers'] ?? []),
        );
    }

    public function baseUrl(): string { return $this->baseUrl; }
    public function apiUser(): string { return $this->apiUser; }
    public function apiPassword(): string { return $this->apiPassword; }
    public function timeout(): int { return $this->timeout; }
    public function retryAttempts(): int { return $this->retryAttempts; }
    public function retryDelayMs(): int { return $this->retryDelayMs; }
    public function userAgent(): string { return $this->userAgent; }
    public function structuredLogsEnabled(): bool { return $this->enableStructuredLogs; }
    public function country(): string { return $this->country; }
    public function publisherExternalId(): ?string { return $this->publisherExternalId; }
    public function customHeaders(): array { return $this->customHeaders; }

    public function redacted(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'api_user' => $this->apiUser !== '' ? '***' : '',
            'api_password' => $this->apiPassword !== '' ? '***' : '',
            'timeout' => $this->timeout,
            'retry_attempts' => $this->retryAttempts,
            'retry_delay_ms' => $this->retryDelayMs,
            'user_agent' => $this->userAgent,
            'country' => $this->country,
            'publisher_external_id' => $this->publisherExternalId,
        ];
    }
}

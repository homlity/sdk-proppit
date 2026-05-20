<?php

declare(strict_types=1);

namespace Propit\Tests\Integration;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Propit\Api\PropertyApi;
use Propit\Api\PublisherApi;
use Propit\Auth\ClientCredentialsAuthenticator;
use Propit\Config\PropitConfig;
use Propit\Contracts\PropertyApiInterface;
use Propit\Contracts\PropertyPayloadMapperInterface;
use Propit\Contracts\PropitAuthenticatorInterface;
use Propit\Contracts\PropitHttpClientInterface;
use Propit\Contracts\PublisherApiInterface;
use Propit\Http\GuzzlePropitHttpClient;
use Propit\Laravel\PropitServiceProvider;
use Propit\Normalizers\PropertyPayloadNormalizer;
use Propit\PropitClient;

final class ContainerBindingTest extends TestCase
{
    private Container $app;

    protected function setUp(): void
    {
        $this->app = new Container();
        $this->app->instance('config', new Repository(['propit' => [
            'base_url'               => 'https://real-time.proppit.com/api/v2',
            'client_id'              => 'test-client-id',
            'client_secret'          => 'test-client-secret',
            'country'                => 'CO',
            'timeout'                => 30,
            'retry_attempts'         => 3,
            'retry_delay_ms'         => 500,
            'enable_structured_logs' => false,
        ]]));

        $provider = new PropitServiceProvider($this->app);
        $provider->register();
    }

    // ── Contract resolution ──────────────────────────────────────────────────

    public function test_PropitClient_resolves(): void
    {
        self::assertInstanceOf(PropitClient::class, $this->app->make(PropitClient::class));
    }

    public function test_PropertyApiInterface_resolves(): void
    {
        self::assertInstanceOf(PropertyApiInterface::class, $this->app->make(PropertyApiInterface::class));
    }

    public function test_PublisherApiInterface_resolves(): void
    {
        self::assertInstanceOf(PublisherApiInterface::class, $this->app->make(PublisherApiInterface::class));
    }

    public function test_PropitAuthenticatorInterface_resolves(): void
    {
        self::assertInstanceOf(PropitAuthenticatorInterface::class, $this->app->make(PropitAuthenticatorInterface::class));
    }

    public function test_PropitHttpClientInterface_resolves(): void
    {
        self::assertInstanceOf(PropitHttpClientInterface::class, $this->app->make(PropitHttpClientInterface::class));
    }

    public function test_PropertyPayloadMapperInterface_resolves(): void
    {
        self::assertInstanceOf(PropertyPayloadMapperInterface::class, $this->app->make(PropertyPayloadMapperInterface::class));
    }

    public function test_PropitConfig_resolves(): void
    {
        self::assertInstanceOf(PropitConfig::class, $this->app->make(PropitConfig::class));
    }

    // ── Concrete implementations ─────────────────────────────────────────────

    public function test_PropertyApiInterface_resolves_to_PropertyApi(): void
    {
        self::assertInstanceOf(PropertyApi::class, $this->app->make(PropertyApiInterface::class));
    }

    public function test_PublisherApiInterface_resolves_to_PublisherApi(): void
    {
        self::assertInstanceOf(PublisherApi::class, $this->app->make(PublisherApiInterface::class));
    }

    public function test_PropitAuthenticatorInterface_resolves_to_ClientCredentialsAuthenticator(): void
    {
        self::assertInstanceOf(ClientCredentialsAuthenticator::class, $this->app->make(PropitAuthenticatorInterface::class));
    }

    public function test_PropitHttpClientInterface_resolves_to_GuzzlePropitHttpClient(): void
    {
        self::assertInstanceOf(GuzzlePropitHttpClient::class, $this->app->make(PropitHttpClientInterface::class));
    }

    public function test_PropertyPayloadMapperInterface_resolves_to_PropertyPayloadNormalizer(): void
    {
        self::assertInstanceOf(PropertyPayloadNormalizer::class, $this->app->make(PropertyPayloadMapperInterface::class));
    }

    // ── Singleton behaviour ──────────────────────────────────────────────────

    public function test_PropitClient_is_singleton(): void
    {
        $a = $this->app->make(PropitClient::class);
        $b = $this->app->make(PropitClient::class);
        self::assertSame($a, $b);
    }

    public function test_PropitConfig_is_singleton(): void
    {
        $a = $this->app->make(PropitConfig::class);
        $b = $this->app->make(PropitConfig::class);
        self::assertSame($a, $b);
    }

    // ── PropitClient exposes both APIs ───────────────────────────────────────

    public function test_PropitClient_exposes_properties(): void
    {
        $client = $this->app->make(PropitClient::class);
        self::assertInstanceOf(PropertyApiInterface::class, $client->properties());
    }

    public function test_PropitClient_exposes_publishers(): void
    {
        $client = $this->app->make(PropitClient::class);
        self::assertInstanceOf(PublisherApiInterface::class, $client->publishers());
    }

    // ── Config values ────────────────────────────────────────────────────────

    public function test_PropitConfig_reads_country_from_laravel_config(): void
    {
        $config = $this->app->make(PropitConfig::class);
        self::assertSame('CO', $config->country());
    }

    public function test_PropitConfig_reads_base_url_from_laravel_config(): void
    {
        $config = $this->app->make(PropitConfig::class);
        self::assertSame('https://real-time.proppit.com/api/v2', $config->baseUrl());
    }

    public function test_PropitConfig_reads_client_id(): void
    {
        $config = $this->app->make(PropitConfig::class);
        self::assertSame('test-client-id', $config->clientId());
    }

    public function test_PropitConfig_does_not_expose_client_secret_in_redacted(): void
    {
        $config   = $this->app->make(PropitConfig::class);
        $redacted = $config->redacted();

        self::assertSame('***', $redacted['client_secret']);
        self::assertStringNotContainsString('test-client-secret', serialize($redacted));
    }

    public function test_PropitConfig_shows_partial_client_id_in_redacted(): void
    {
        $config   = $this->app->make(PropitConfig::class);
        $redacted = $config->redacted();

        self::assertStringContainsString('***', $redacted['client_id']);
        self::assertStringNotContainsString('test-client-id', $redacted['client_id']);
    }

    // ── Interface replacement via DI ─────────────────────────────────────────

    public function test_PropertyPayloadMapperInterface_can_be_swapped(): void
    {
        $custom = new class implements PropertyPayloadMapperInterface {
            public function normalize(\Propit\DTO\PropertyPayload|array $_payload): array
            {
                return ['custom' => true];
            }
        };

        $this->app->bind(PropertyPayloadMapperInterface::class, fn () => $custom);

        $mapper = $this->app->make(PropertyPayloadMapperInterface::class);
        self::assertSame(['custom' => true], $mapper->normalize([]));
    }

    public function test_PublisherApiInterface_can_be_swapped(): void
    {
        $custom = new class implements PublisherApiInterface {
            public function create(\Propit\DTO\PublisherPayload $_p): \Propit\DTO\PublisherResponse
            {
                return \Propit\DTO\PublisherResponse::fromArray(['id' => 'custom']);
            }

            public function update(string $_id, \Propit\DTO\PublisherPayload $_p): \Propit\DTO\PublisherResponse
            {
                return \Propit\DTO\PublisherResponse::fromArray(['id' => 'custom']);
            }

            public function find(string $_id): ?\Propit\DTO\PublisherResponse
            {
                return null;
            }

            public function createOrUpdate(\Propit\DTO\PublisherPayload $_p): \Propit\DTO\PublisherResponse
            {
                return \Propit\DTO\PublisherResponse::fromArray(['id' => 'custom']);
            }

            public function status(string $_id): \Propit\DTO\PublisherResponse
            {
                return \Propit\DTO\PublisherResponse::fromArray(['id' => 'custom']);
            }
        };

        $this->app->bind(PublisherApiInterface::class, fn () => $custom);

        $result = $this->app->make(PublisherApiInterface::class)->find('x');
        self::assertNull($result);
    }

    // ── Logs sanitization ────────────────────────────────────────────────────

    public function test_StructuredLogger_sanitize_redacts_client_secret(): void
    {
        $sanitized = \Propit\Support\StructuredLogger::sanitize([
            'client_secret' => 'my-secret',
            'client_id'     => 'my-id',
            'Authorization' => 'Bearer tok',
        ]);

        self::assertSame('***', $sanitized['client_secret']);
        self::assertSame('***', $sanitized['Authorization']);
        self::assertStringContainsString('***', $sanitized['client_id']);
        self::assertStringNotContainsString('my-secret', serialize($sanitized));
    }

    public function test_StructuredLogger_sanitize_redacts_access_token(): void
    {
        $sanitized = \Propit\Support\StructuredLogger::sanitize([
            'access_token'  => 'abc123',
            'refresh_token' => 'xyz789',
        ]);

        self::assertSame('***', $sanitized['access_token']);
        self::assertSame('***', $sanitized['refresh_token']);
    }
}

<?php

declare(strict_types=1);

namespace Proppit\Tests\Integration;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Proppit\Api\PropertyApi;
use Proppit\Api\PublisherApi;
use Proppit\Auth\ClientCredentialsAuthenticator;
use Proppit\Config\ProppitConfig;
use Proppit\Contracts\PropertyApiInterface;
use Proppit\Contracts\PropertyPayloadMapperInterface;
use Proppit\Contracts\ProppitAuthenticatorInterface;
use Proppit\Contracts\ProppitHttpClientInterface;
use Proppit\Contracts\PublisherApiInterface;
use Proppit\Http\GuzzleProppitHttpClient;
use Proppit\Laravel\ProppitServiceProvider;
use Proppit\Normalizers\PropertyPayloadNormalizer;
use Proppit\ProppitClient;

final class ContainerBindingTest extends TestCase
{
    private Container $app;

    protected function setUp(): void
    {
        $this->app = new Container();
        $this->app->instance('config', new Repository(['proppit' => [
            'base_url'               => 'https://real-time.proppit.com/api/v2',
            'client_id'              => 'test-client-id',
            'client_secret'          => 'test-client-secret',
            'country'                => 'CO',
            'timeout'                => 30,
            'retry_attempts'         => 3,
            'retry_delay_ms'         => 500,
            'enable_structured_logs' => false,
        ]]));

        $provider = new ProppitServiceProvider($this->app);
        $provider->register();
    }

    // ── Contract resolution ──────────────────────────────────────────────────

    public function test_ProppitClient_resolves(): void
    {
        self::assertInstanceOf(ProppitClient::class, $this->app->make(ProppitClient::class));
    }

    public function test_PropertyApiInterface_resolves(): void
    {
        self::assertInstanceOf(PropertyApiInterface::class, $this->app->make(PropertyApiInterface::class));
    }

    public function test_PublisherApiInterface_resolves(): void
    {
        self::assertInstanceOf(PublisherApiInterface::class, $this->app->make(PublisherApiInterface::class));
    }

    public function test_ProppitAuthenticatorInterface_resolves(): void
    {
        self::assertInstanceOf(ProppitAuthenticatorInterface::class, $this->app->make(ProppitAuthenticatorInterface::class));
    }

    public function test_ProppitHttpClientInterface_resolves(): void
    {
        self::assertInstanceOf(ProppitHttpClientInterface::class, $this->app->make(ProppitHttpClientInterface::class));
    }

    public function test_PropertyPayloadMapperInterface_resolves(): void
    {
        self::assertInstanceOf(PropertyPayloadMapperInterface::class, $this->app->make(PropertyPayloadMapperInterface::class));
    }

    public function test_ProppitConfig_resolves(): void
    {
        self::assertInstanceOf(ProppitConfig::class, $this->app->make(ProppitConfig::class));
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

    public function test_ProppitAuthenticatorInterface_resolves_to_ClientCredentialsAuthenticator(): void
    {
        self::assertInstanceOf(ClientCredentialsAuthenticator::class, $this->app->make(ProppitAuthenticatorInterface::class));
    }

    public function test_ProppitHttpClientInterface_resolves_to_GuzzleProppitHttpClient(): void
    {
        self::assertInstanceOf(GuzzleProppitHttpClient::class, $this->app->make(ProppitHttpClientInterface::class));
    }

    public function test_PropertyPayloadMapperInterface_resolves_to_PropertyPayloadNormalizer(): void
    {
        self::assertInstanceOf(PropertyPayloadNormalizer::class, $this->app->make(PropertyPayloadMapperInterface::class));
    }

    // ── Singleton behaviour ──────────────────────────────────────────────────

    public function test_ProppitClient_is_singleton(): void
    {
        $a = $this->app->make(ProppitClient::class);
        $b = $this->app->make(ProppitClient::class);
        self::assertSame($a, $b);
    }

    public function test_ProppitConfig_is_singleton(): void
    {
        $a = $this->app->make(ProppitConfig::class);
        $b = $this->app->make(ProppitConfig::class);
        self::assertSame($a, $b);
    }

    // ── ProppitClient exposes both APIs ───────────────────────────────────────

    public function test_ProppitClient_exposes_properties(): void
    {
        $client = $this->app->make(ProppitClient::class);
        self::assertInstanceOf(PropertyApiInterface::class, $client->properties());
    }

    public function test_ProppitClient_exposes_publishers(): void
    {
        $client = $this->app->make(ProppitClient::class);
        self::assertInstanceOf(PublisherApiInterface::class, $client->publishers());
    }

    // ── Config values ────────────────────────────────────────────────────────

    public function test_ProppitConfig_reads_country_from_laravel_config(): void
    {
        $config = $this->app->make(ProppitConfig::class);
        self::assertSame('CO', $config->country());
    }

    public function test_ProppitConfig_reads_base_url_from_laravel_config(): void
    {
        $config = $this->app->make(ProppitConfig::class);
        self::assertSame('https://real-time.proppit.com/api/v2', $config->baseUrl());
    }

    public function test_ProppitConfig_reads_client_id(): void
    {
        $config = $this->app->make(ProppitConfig::class);
        self::assertSame('test-client-id', $config->clientId());
    }

    public function test_ProppitConfig_does_not_expose_client_secret_in_redacted(): void
    {
        $config   = $this->app->make(ProppitConfig::class);
        $redacted = $config->redacted();

        self::assertSame('***', $redacted['client_secret']);
        self::assertStringNotContainsString('test-client-secret', serialize($redacted));
    }

    public function test_ProppitConfig_shows_partial_client_id_in_redacted(): void
    {
        $config   = $this->app->make(ProppitConfig::class);
        $redacted = $config->redacted();

        self::assertStringContainsString('***', $redacted['client_id']);
        self::assertStringNotContainsString('test-client-id', $redacted['client_id']);
    }

    // ── Interface replacement via DI ─────────────────────────────────────────

    public function test_PropertyPayloadMapperInterface_can_be_swapped(): void
    {
        $custom = new class implements PropertyPayloadMapperInterface {
            public function normalize(\Proppit\DTO\PropertyPayload|array $_payload): array
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
            public function create(\Proppit\DTO\PublisherPayload $_p): \Proppit\DTO\PublisherResponse
            {
                return \Proppit\DTO\PublisherResponse::fromArray(['id' => 'custom']);
            }

            public function update(string $_id, \Proppit\DTO\PublisherPayload $_p): \Proppit\DTO\PublisherResponse
            {
                return \Proppit\DTO\PublisherResponse::fromArray(['id' => 'custom']);
            }

            public function find(string $_id): ?\Proppit\DTO\PublisherResponse
            {
                return null;
            }

            public function createOrUpdate(\Proppit\DTO\PublisherPayload $_p): \Proppit\DTO\PublisherResponse
            {
                return \Proppit\DTO\PublisherResponse::fromArray(['id' => 'custom']);
            }

            public function status(string $_id): \Proppit\DTO\PublisherResponse
            {
                return \Proppit\DTO\PublisherResponse::fromArray(['id' => 'custom']);
            }
        };

        $this->app->bind(PublisherApiInterface::class, fn () => $custom);

        $result = $this->app->make(PublisherApiInterface::class)->find('x');
        self::assertNull($result);
    }

    // ── Logs sanitization ────────────────────────────────────────────────────

    public function test_StructuredLogger_sanitize_redacts_client_secret(): void
    {
        $sanitized = \Proppit\Support\StructuredLogger::sanitize([
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
        $sanitized = \Proppit\Support\StructuredLogger::sanitize([
            'access_token'  => 'abc123',
            'refresh_token' => 'xyz789',
        ]);

        self::assertSame('***', $sanitized['access_token']);
        self::assertSame('***', $sanitized['refresh_token']);
    }
}

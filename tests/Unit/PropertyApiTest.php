<?php

declare(strict_types=1);

namespace Propit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Propit\Api\PropertyApi;
use Propit\Config\PropitConfig;
use Propit\Contracts\PropitHttpClientInterface;
use Propit\DTO\HttpResponse;
use Propit\Exceptions\ApiException;
use Propit\Exceptions\AuthException;
use Propit\Exceptions\PublisherNotReadyException;
use Propit\Exceptions\PublisherPermissionException;
use Propit\Exceptions\RateLimitException;
use Propit\Exceptions\ValidationException;
use Propit\Normalizers\PropertyPayloadNormalizer;

final class PropertyApiTest extends TestCase
{
    private PropitConfig $cfg;
    private PropertyPayloadNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->cfg = PropitConfig::fromArray([
            'base_url'              => 'https://real-time.proppit.com/api/v2',
            'client_id'             => 'u',
            'client_secret'         => 'p',
            'publisher_external_id' => 'pub-1',
        ]);

        $this->normalizer = new PropertyPayloadNormalizer();
    }

    // ── Minimal valid payload fixture ────────────────────────────────────────

    private function validPayload(): array
    {
        return [
            'referenceId' => 'CO-1',
            'publisher'   => ['externalId' => 'pub-1'],
            'property'    => [
                'type'     => 'apartment',
                'location' => ['coordinates' => ['lat' => 4.711, 'long' => -74.0721]],
            ],
            'operations'  => [['type' => 'sell', 'price' => ['value' => 450_000_000, 'currency' => 'COP']]],
            'title'       => ['locale' => 'es-CO', 'text' => 'Apartamento'],
            'description' => ['locale' => 'es-CO', 'text' => 'Desc'],
        ];
    }

    // ── HTTP transport fake ──────────────────────────────────────────────────

    private function fakeHttp(int $status, array $json = []): PropitHttpClientInterface
    {
        return new class($status, $json) implements PropitHttpClientInterface {
            public function __construct(private int $status, private array $json)
            {
            }

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                unset($method, $uri, $headers, $query, $json);
                return new HttpResponse($this->status, [], '{}', $this->json);
            }
        };
    }

    /**
     * Captures the last request made so assertions can inspect method/uri/body.
     */
    private function capturingHttp(int $status, array $json, array &$captured): PropitHttpClientInterface
    {
        return new class($status, $json, $captured) implements PropitHttpClientInterface {
            public function __construct(
                private int $status,
                private array $json,
                private array &$captured,
            ) {
            }

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                $this->captured = compact('method', 'uri', 'query', 'json');
                return new HttpResponse($this->status, [], '{}', $this->json);
            }
        };
    }

    /** Always throws the given exception. */
    private function throwingHttp(\Throwable $e): PropitHttpClientInterface
    {
        return new class($e) implements PropitHttpClientInterface {
            public function __construct(private \Throwable $e)
            {
            }

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                unset($method, $uri, $headers, $query, $json);
                throw $this->e;
            }
        };
    }

    // ── publish ──────────────────────────────────────────────────────────────

    public function test_publish_calls_POST_on_ads_path(): void
    {
        $captured = [];
        $api      = new PropertyApi($this->capturingHttp(201, ['referenceId' => 'CO-1'], $captured), $this->normalizer, $this->cfg);

        $api->publish($this->validPayload());

        self::assertSame('POST', $captured['method']);
        self::assertSame('/proppit/CO/ads', $captured['uri']);
        self::assertSame('CO-1', $captured['json']['referenceId']);
    }

    public function test_publish_returns_PropertyResponse_with_referenceId(): void
    {
        $api      = new PropertyApi($this->fakeHttp(201, ['referenceId' => 'CO-1', 'status' => 'published']), $this->normalizer, $this->cfg);
        $response = $api->publish($this->validPayload());

        self::assertSame('CO-1', $response->referenceId);
        self::assertSame('published', $response->status);
    }

    public function test_publish_with_invalid_payload_throws_ValidationException(): void
    {
        $api = new PropertyApi($this->fakeHttp(201), $this->normalizer, $this->cfg);

        $this->expectException(ValidationException::class);
        $api->publish(['referenceId' => 'only-this']);
    }

    // ── update ───────────────────────────────────────────────────────────────

    public function test_update_calls_PUT_on_ad_path(): void
    {
        $captured = [];
        $api      = new PropertyApi($this->capturingHttp(200, ['referenceId' => 'CO-1'], $captured), $this->normalizer, $this->cfg);

        $api->update('CO-1', $this->validPayload());

        self::assertSame('PUT', $captured['method']);
        self::assertStringContainsString('/proppit/CO/ads/CO-1', $captured['uri']);
    }

    public function test_update_returns_PropertyResponse(): void
    {
        $api      = new PropertyApi($this->fakeHttp(200, ['referenceId' => 'CO-1', 'status' => 'published']), $this->normalizer, $this->cfg);
        $response = $api->update('CO-1', $this->validPayload());

        self::assertSame('CO-1', $response->referenceId);
    }

    // ── find ─────────────────────────────────────────────────────────────────

    public function test_find_calls_GET_with_externalId_query(): void
    {
        $captured = [];
        $api      = new PropertyApi($this->capturingHttp(200, ['referenceId' => 'CO-1'], $captured), $this->normalizer, $this->cfg);

        $api->find('CO-1');

        self::assertSame('GET', $captured['method']);
        self::assertSame('pub-1', $captured['query']['externalId']);
    }

    public function test_find_without_publisher_external_id_throws_ValidationException(): void
    {
        $cfgNoPublisher = PropitConfig::fromArray([
            'base_url'    => 'https://real-time.proppit.com/api/v2',
            'client_id'     => 'u',
            'client_secret' => 'p',
        ]);

        $api = new PropertyApi($this->fakeHttp(200, ['referenceId' => 'CO-1']), $this->normalizer, $cfgNoPublisher);

        $this->expectException(ValidationException::class);
        $api->find('CO-1');
    }

    // ── findByExternalId ─────────────────────────────────────────────────────

    public function test_findByExternalId_returns_null_on_empty_json(): void
    {
        $api    = new PropertyApi($this->fakeHttp(200, []), $this->normalizer, $this->cfg);
        $result = $api->findByExternalId('pub-1', 'CO-1');

        self::assertNull($result);
    }

    public function test_findByExternalId_returns_PropertyResponse_on_match(): void
    {
        $api    = new PropertyApi($this->fakeHttp(200, ['referenceId' => 'CO-1', 'status' => 'published']), $this->normalizer, $this->cfg);
        $result = $api->findByExternalId('pub-1', 'CO-1');

        self::assertNotNull($result);
        self::assertSame('CO-1', $result->referenceId);
    }

    // ── delete ───────────────────────────────────────────────────────────────

    public function test_delete_calls_DELETE_on_ad_path(): void
    {
        $captured = [];
        $api      = new PropertyApi($this->capturingHttp(200, [], $captured), $this->normalizer, $this->cfg);

        $api->delete('CO-1');

        self::assertSame('DELETE', $captured['method']);
        self::assertStringContainsString('/proppit/CO/ads/CO-1', $captured['uri']);
        self::assertSame('pub-1', $captured['query']['externalId']);
    }

    public function test_delete_returns_deleted_status(): void
    {
        $api      = new PropertyApi($this->fakeHttp(200, []), $this->normalizer, $this->cfg);
        $response = $api->delete('CO-1');

        self::assertSame('deleted', $response->status);
        self::assertSame('CO-1', $response->referenceId);
    }

    public function test_delete_without_publisher_external_id_throws_ValidationException(): void
    {
        $cfgNoPublisher = PropitConfig::fromArray([
            'base_url'    => 'https://real-time.proppit.com/api/v2',
            'client_id'     => 'u',
            'client_secret' => 'p',
        ]);

        $api = new PropertyApi($this->fakeHttp(200, []), $this->normalizer, $cfgNoPublisher);

        $this->expectException(ValidationException::class);
        $api->delete('CO-1');
    }

    // ── HTTP error propagation ───────────────────────────────────────────────

    public function test_publish_propagates_AuthException_on_401(): void
    {
        $exception = new AuthException('Unauthorized.', [], 401);
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        $this->expectException(AuthException::class);
        $api->publish($this->validPayload());
    }

    public function test_publish_propagates_AuthException_on_403(): void
    {
        $exception = new AuthException('Forbidden.', [], 403);
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        $this->expectException(AuthException::class);
        $api->publish($this->validPayload());
    }

    public function test_publish_enriches_PublisherNotReadyException_with_externalId_from_payload(): void
    {
        $exception = new PublisherNotReadyException('', 'req-abc123', []);
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        try {
            $api->publish($this->validPayload());
            self::fail('Expected PublisherNotReadyException');
        } catch (PublisherNotReadyException $e) {
            self::assertSame('pub-1', $e->publisherExternalId);
            self::assertSame('req-abc123', $e->proppitRequestId);
        }
    }

    public function test_update_enriches_PublisherNotReadyException_with_externalId_from_payload(): void
    {
        $exception = new PublisherNotReadyException('', 'req-xyz789', []);
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        try {
            $api->update('CO-1', $this->validPayload());
            self::fail('Expected PublisherNotReadyException');
        } catch (PublisherNotReadyException $e) {
            self::assertSame('pub-1', $e->publisherExternalId);
            self::assertSame('req-xyz789', $e->proppitRequestId);
        }
    }

    public function test_publish_propagates_RateLimitException_on_429(): void
    {
        $exception = new RateLimitException('Rate limit.', 429, 'POST', '/proppit/CO/ads', 60);
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        $this->expectException(RateLimitException::class);
        $api->publish($this->validPayload());
    }

    public function test_publish_propagates_ApiException_on_400(): void
    {
        $exception = new ApiException('Bad request.', 400, 'POST', '/proppit/CO/ads');
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        $this->expectException(ApiException::class);
        $api->publish($this->validPayload());
    }

    public function test_publish_propagates_ApiException_on_500(): void
    {
        $exception = new ApiException('Server error.', 500, 'POST', '/proppit/CO/ads');
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        $this->expectException(ApiException::class);
        $api->publish($this->validPayload());
    }

    // ── country routing ──────────────────────────────────────────────────────

    public function test_country_is_injected_in_url(): void
    {
        $cfg = PropitConfig::fromArray([
            'base_url'              => 'https://real-time.proppit.com/api/v2',
            'client_id'             => 'u',
            'client_secret'         => 'p',
            'country'               => 'MX',
            'publisher_external_id' => 'pub-mx',
        ]);

        $captured = [];
        $api      = new PropertyApi($this->capturingHttp(201, ['referenceId' => 'MX-1'], $captured), $this->normalizer, $cfg);

        $payload                              = $this->validPayload();
        $payload['title']['locale']           = 'es-MX';
        $payload['description']['locale']     = 'es-MX';
        $payload['operations'][0]['price']['currency'] = 'MXN';

        $api->publish($payload);

        self::assertStringContainsString('/proppit/MX/ads', $captured['uri']);
    }

    // ── referenceId URL encoding ─────────────────────────────────────────────

    public function test_referenceId_with_special_chars_is_url_encoded(): void
    {
        $captured = [];
        $api      = new PropertyApi($this->capturingHttp(200, ['referenceId' => 'CO 1/X'], $captured), $this->normalizer, $this->cfg);

        $api->update('CO 1/X', $this->validPayload());

        self::assertStringContainsString('CO%201%2FX', $captured['uri']);
    }

    // ── PublisherPermissionException hierarchy ────────────────────────────────

    public function test_publish_PublisherNotReadyException_is_instance_of_PublisherPermissionException(): void
    {
        $exception = new PublisherNotReadyException('homlity_agency_1', 'req-xyz', []);
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        try {
            $api->publish($this->validPayload());
            self::fail('Expected exception');
        } catch (PublisherPermissionException $e) {
            self::assertInstanceOf(PublisherNotReadyException::class, $e);
        }
    }

    public function test_publish_PublisherPermissionException_preserves_requestId(): void
    {
        $exception = new PublisherNotReadyException('homlity_agency_1', 'req-test-456', []);
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        try {
            $api->publish($this->validPayload());
            self::fail('Expected exception');
        } catch (PublisherPermissionException $e) {
            self::assertSame('req-test-456', $e->requestId());
        }
    }

    public function test_publish_PublisherPermissionException_is_NOT_AuthException(): void
    {
        $exception = new PublisherNotReadyException('homlity_agency_1', 'req-xyz', []);
        $api       = new PropertyApi($this->throwingHttp($exception), $this->normalizer, $this->cfg);

        try {
            $api->publish($this->validPayload());
            self::fail('Expected exception');
        } catch (PublisherPermissionException $e) {
            self::assertNotInstanceOf(AuthException::class, $e);
        }
    }

    public function test_publish_does_not_retry_indefinitely_on_publisher_permission_error(): void
    {
        $callCount = 0;
        $http      = new class($callCount) implements \Propit\Contracts\PropitHttpClientInterface {
            public function __construct(private int &$count) {}

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): \Propit\DTO\HttpResponse
            {
                $this->count++;
                throw new PublisherNotReadyException('homlity_agency_1', null, []);
            }
        };

        $api = new PropertyApi($http, $this->normalizer, $this->cfg);

        try {
            $api->publish($this->validPayload());
        } catch (PublisherPermissionException) {
            // expected
        }

        // The API layer must NOT retry on PublisherPermissionException — it is not a
        // transient error. Retrying won't help until Proppit activates the publisher.
        self::assertSame(1, $callCount, 'Should not retry on PublisherPermissionException');
    }

    public function test_publish_can_succeed_after_publisher_is_activated(): void
    {
        // Simulates: first call fails (publisher not ready), second succeeds (activated)
        $calls = 0;
        $http  = new class($calls) implements \Propit\Contracts\PropitHttpClientInterface {
            public function __construct(private int &$calls) {}

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): \Propit\DTO\HttpResponse
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new PublisherNotReadyException('homlity_agency_1', null, []);
                }
                return new \Propit\DTO\HttpResponse(201, [], '{}', ['referenceId' => 'CO-1', 'status' => 'published']);
            }
        };

        $api = new PropertyApi($http, $this->normalizer, $this->cfg);

        // First attempt — publisher not ready
        try {
            $api->publish($this->validPayload());
            self::fail('Expected PublisherPermissionException on first call');
        } catch (PublisherPermissionException) {
            // expected — save status, wait for Proppit to activate
        }

        // Second attempt — after Proppit activates the publisher
        $response = $api->publish($this->validPayload());
        self::assertSame('CO-1', $response->referenceId);
        self::assertSame(2, $calls);
    }
}

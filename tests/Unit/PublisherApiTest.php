<?php

declare(strict_types=1);

namespace Proppit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Proppit\Api\PublisherApi;
use Proppit\Config\ProppitConfig;
use Proppit\Contracts\ProppitHttpClientInterface;
use Proppit\DTO\HttpResponse;
use Proppit\DTO\PublisherPayload;
use Proppit\DTO\PublisherResponse;
use Proppit\Exceptions\ApiException;
use Proppit\Exceptions\AuthException;
use Proppit\Exceptions\ValidationException;
use Proppit\Normalizers\PublisherPayloadNormalizer;

final class PublisherApiTest extends TestCase
{
    private ProppitConfig $config;
    private PublisherPayloadNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->config = ProppitConfig::fromArray([
            'base_url'      => 'https://real-time.proppit.com/api/v2',
            'client_id'     => 'id',
            'client_secret' => 'secret',
            'country'       => 'CO',
        ]);

        $this->normalizer = new PublisherPayloadNormalizer();
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function validPayload(): PublisherPayload
    {
        return new PublisherPayload(
            externalId: 'homlity_agency_42',
            name: 'Inmobiliaria Demo',
            email: 'contacto@demo.com',
            phone: '+573001112233',
        );
    }

    private function publisherJson(string $id = 'homlity_agency_42'): array
    {
        return [
            'id'    => $id,
            'name'  => 'Inmobiliaria Demo',
            'email' => 'contacto@demo.com',
            'phone' => '+573001112233',
        ];
    }

    // ── HTTP fakes ────────────────────────────────────────────────────────────

    private function fakeHttp(int $status, array $json = []): ProppitHttpClientInterface
    {
        return new class($status, $json) implements ProppitHttpClientInterface {
            public function __construct(private int $status, private array $json) {}

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                return new HttpResponse($this->status, [], '{}', $this->json);
            }
        };
    }

    private function capturingHttp(int $status, array $json, array &$captured): ProppitHttpClientInterface
    {
        return new class($status, $json, $captured) implements ProppitHttpClientInterface {
            public function __construct(
                private int $status,
                private array $json,
                private array &$captured,
            ) {}

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                $this->captured = compact('method', 'uri', 'query', 'json');
                return new HttpResponse($this->status, [], '{}', $this->json);
            }
        };
    }

    private function throwingHttp(\Throwable $e): ProppitHttpClientInterface
    {
        return new class($e) implements ProppitHttpClientInterface {
            public function __construct(private \Throwable $e) {}

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                throw $this->e;
            }
        };
    }

    /** Returns 404 on GET, then 201 on POST. */
    private function notFoundThenCreated(): ProppitHttpClientInterface
    {
        return new class($this->publisherJson()) implements ProppitHttpClientInterface {
            private int $calls = 0;

            public function __construct(private array $json) {}

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new ApiException('Not found.', 404, $method, $uri);
                }
                return new HttpResponse(201, [], '{}', $this->json);
            }
        };
    }

    /** Returns 200 on GET, then 200 on PUT. */
    private function foundThenUpdated(): ProppitHttpClientInterface
    {
        return new class($this->publisherJson()) implements ProppitHttpClientInterface {
            public function __construct(private array $json) {}

            public function request(string $method, string $uri, array $headers = [], array $query = [], array $json = []): HttpResponse
            {
                return new HttpResponse(200, [], '{}', $this->json);
            }
        };
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function test_create_calls_POST_on_publishers_path(): void
    {
        $captured = [];
        $api      = new PublisherApi($this->capturingHttp(201, $this->publisherJson(), $captured), $this->normalizer, $this->config);

        $api->create($this->validPayload());

        self::assertSame('POST', $captured['method']);
        self::assertSame('/proppit/CO/publishers', $captured['uri']);
    }

    public function test_create_sends_id_field_from_externalId(): void
    {
        $captured = [];
        $api      = new PublisherApi($this->capturingHttp(201, $this->publisherJson(), $captured), $this->normalizer, $this->config);

        $api->create($this->validPayload());

        self::assertSame('homlity_agency_42', $captured['json']['id']);
        self::assertArrayNotHasKey('externalId', $captured['json']);
    }

    public function test_create_returns_PublisherResponse_with_publisherId(): void
    {
        $api      = new PublisherApi($this->fakeHttp(201, $this->publisherJson()), $this->normalizer, $this->config);
        $response = $api->create($this->validPayload());

        self::assertInstanceOf(PublisherResponse::class, $response);
        self::assertSame('homlity_agency_42', $response->publisherId());
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_calls_PUT_on_publisher_path(): void
    {
        $captured = [];
        $api      = new PublisherApi($this->capturingHttp(200, $this->publisherJson(), $captured), $this->normalizer, $this->config);

        $api->update('homlity_agency_42', $this->validPayload());

        self::assertSame('PUT', $captured['method']);
        self::assertStringContainsString('/proppit/CO/publishers/homlity_agency_42', $captured['uri']);
    }

    public function test_update_returns_PublisherResponse(): void
    {
        $api      = new PublisherApi($this->fakeHttp(200, $this->publisherJson()), $this->normalizer, $this->config);
        $response = $api->update('homlity_agency_42', $this->validPayload());

        self::assertSame('homlity_agency_42', $response->publisherId());
    }

    // ── find ─────────────────────────────────────────────────────────────────

    public function test_find_calls_GET_on_publisher_path(): void
    {
        $captured = [];
        $api      = new PublisherApi($this->capturingHttp(200, $this->publisherJson(), $captured), $this->normalizer, $this->config);

        $api->find('homlity_agency_42');

        self::assertSame('GET', $captured['method']);
        self::assertStringContainsString('/proppit/CO/publishers/homlity_agency_42', $captured['uri']);
    }

    public function test_find_returns_null_on_404(): void
    {
        $http = $this->throwingHttp(new ApiException('Not found.', 404, 'GET', '/proppit/CO/publishers/x'));
        $api  = new PublisherApi($http, $this->normalizer, $this->config);

        self::assertNull($api->find('x'));
    }

    public function test_find_returns_null_on_empty_json(): void
    {
        $api    = new PublisherApi($this->fakeHttp(200, []), $this->normalizer, $this->config);
        $result = $api->find('homlity_agency_42');

        self::assertNull($result);
    }

    public function test_find_returns_PublisherResponse_on_match(): void
    {
        $api    = new PublisherApi($this->fakeHttp(200, $this->publisherJson()), $this->normalizer, $this->config);
        $result = $api->find('homlity_agency_42');

        self::assertNotNull($result);
        self::assertSame('homlity_agency_42', $result->publisherId());
    }

    public function test_find_propagates_non_404_ApiException(): void
    {
        $http = $this->throwingHttp(new ApiException('Server error.', 500, 'GET', '/proppit/CO/publishers/x'));
        $api  = new PublisherApi($http, $this->normalizer, $this->config);

        $this->expectException(ApiException::class);
        $api->find('x');
    }

    // ── createOrUpdate ────────────────────────────────────────────────────────

    public function test_createOrUpdate_creates_when_publisher_not_found(): void
    {
        $api      = new PublisherApi($this->notFoundThenCreated(), $this->normalizer, $this->config);
        $response = $api->createOrUpdate($this->validPayload());

        self::assertSame('homlity_agency_42', $response->publisherId());
    }

    public function test_createOrUpdate_updates_when_publisher_exists(): void
    {
        $api      = new PublisherApi($this->foundThenUpdated(), $this->normalizer, $this->config);
        $response = $api->createOrUpdate($this->validPayload());

        self::assertSame('homlity_agency_42', $response->publisherId());
    }

    // ── externalId validation on create ──────────────────────────────────────

    public function test_create_with_empty_externalId_throws_ValidationException(): void
    {
        $api = new PublisherApi($this->fakeHttp(201), $this->normalizer, $this->config);

        $this->expectException(ValidationException::class);
        $api->create(new PublisherPayload(externalId: '', name: 'A', email: 'a@b.com'));
    }

    // ── error propagation ─────────────────────────────────────────────────────

    public function test_create_propagates_AuthException_on_401(): void
    {
        $http = $this->throwingHttp(new AuthException('Unauthorized.', [], 401));
        $api  = new PublisherApi($http, $this->normalizer, $this->config);

        $this->expectException(AuthException::class);
        $api->create($this->validPayload());
    }

    // ── country routing ───────────────────────────────────────────────────────

    public function test_country_is_injected_in_publishers_url(): void
    {
        $cfg = ProppitConfig::fromArray([
            'base_url'      => 'https://real-time.proppit.com/api/v2',
            'client_id'     => 'id',
            'client_secret' => 's',
            'country'       => 'MX',
        ]);

        $captured = [];
        $api      = new PublisherApi($this->capturingHttp(201, $this->publisherJson(), $captured), $this->normalizer, $cfg);

        $api->create($this->validPayload());

        self::assertStringContainsString('/proppit/MX/publishers', $captured['uri']);
    }

    // ── status ───────────────────────────────────────────────────────────────

    public function test_status_returns_PublisherResponse_when_publisher_exists(): void
    {
        $api    = new PublisherApi($this->fakeHttp(200, $this->publisherJson()), $this->normalizer, $this->config);
        $result = $api->status('homlity_agency_42');

        self::assertInstanceOf(PublisherResponse::class, $result);
        self::assertSame('homlity_agency_42', $result->publisherId());
    }

    public function test_status_exposes_raw_response_for_inspection(): void
    {
        $json = $this->publisherJson() + ['active' => false, 'state' => 'pending_activation'];
        $api  = new PublisherApi($this->fakeHttp(200, $json), $this->normalizer, $this->config);

        $result = $api->status('homlity_agency_42');

        self::assertSame(false, $result->raw['active']);
        self::assertSame('pending_activation', $result->raw['state']);
    }

    public function test_status_throws_ApiException_when_publisher_not_found(): void
    {
        $http = $this->throwingHttp(new ApiException('Not found.', 404, 'GET', '/proppit/CO/publishers/x'));
        $api  = new PublisherApi($http, $this->normalizer, $this->config);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);
        $api->status('x');
    }

    // ── publisherId URL-encoding ──────────────────────────────────────────────

    public function test_publisherId_with_special_chars_is_url_encoded(): void
    {
        $captured = [];
        $api      = new PublisherApi($this->capturingHttp(200, $this->publisherJson('id/1'), $captured), $this->normalizer, $this->config);

        $api->find('id/1');

        self::assertStringContainsString('id%2F1', $captured['uri']);
    }

    // ── Activation states in PublisherResponse ────────────────────────────────

    public function test_create_returns_pending_activation_by_default(): void
    {
        $api      = new PublisherApi($this->fakeHttp(201, $this->publisherJson()), $this->normalizer, $this->config);
        $response = $api->create($this->validPayload());

        self::assertTrue($response->isPendingActivation());
        self::assertFalse($response->canPublish());
    }

    public function test_create_returns_active_when_proppit_confirms_canPublish(): void
    {
        $json = $this->publisherJson() + ['canPublish' => true];
        $api  = new PublisherApi($this->fakeHttp(201, $json), $this->normalizer, $this->config);

        $response = $api->create($this->validPayload());

        self::assertTrue($response->canPublish());
        self::assertFalse($response->isPendingActivation());
    }

    public function test_update_returns_pending_activation_by_default(): void
    {
        $api      = new PublisherApi($this->fakeHttp(200, $this->publisherJson()), $this->normalizer, $this->config);
        $response = $api->update('homlity_agency_42', $this->validPayload());

        self::assertTrue($response->isPendingActivation());
        self::assertFalse($response->canPublish());
    }

    public function test_createOrUpdate_returns_pending_activation_when_no_confirmation(): void
    {
        $api      = new PublisherApi($this->notFoundThenCreated(), $this->normalizer, $this->config);
        $response = $api->createOrUpdate($this->validPayload());

        self::assertTrue($response->isPendingActivation());
    }

    public function test_status_returns_active_when_proppit_says_active(): void
    {
        $json = $this->publisherJson() + ['active' => true];
        $api  = new PublisherApi($this->fakeHttp(200, $json), $this->normalizer, $this->config);

        $result = $api->status('homlity_agency_42');

        self::assertTrue($result->canPublish());
        self::assertFalse($result->isPendingActivation());
    }

    public function test_status_returns_pending_activation_when_active_false(): void
    {
        $json = $this->publisherJson() + ['active' => false, 'state' => 'pending_activation'];
        $api  = new PublisherApi($this->fakeHttp(200, $json), $this->normalizer, $this->config);

        $result = $api->status('homlity_agency_42');

        self::assertFalse($result->canPublish());
        self::assertTrue($result->isPendingActivation());
    }
}

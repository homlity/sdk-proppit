<?php

declare(strict_types=1);

namespace Proppit\Tests\Unit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Proppit\Auth\ClientCredentialsAuthenticator;
use Proppit\Config\ProppitConfig;
use Proppit\Contracts\ProppitAuthenticatorInterface;
use Proppit\Exceptions\ApiException;
use Proppit\Exceptions\AuthException;
use Proppit\Exceptions\ForbiddenException;
use Proppit\Exceptions\PublisherNotReadyException;
use Proppit\Exceptions\PublisherPermissionException;
use Proppit\Exceptions\RateLimitException;
use Proppit\Http\GuzzleProppitHttpClient;
use Proppit\Support\StructuredLogger;

final class GuzzleProppitHttpClientTest extends TestCase
{
    private ProppitConfig $config;

    protected function setUp(): void
    {
        $this->config = ProppitConfig::fromArray([
            'base_url'      => 'https://real-time.proppit.com/api/v2',
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'country'       => 'CO',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeClient(int $status, array $body = [], array $headers = []): GuzzleProppitHttpClient
    {
        $json    = json_encode($body);
        $resp    = new Response($status, array_merge(['Content-Type' => 'application/json'], $headers), $json);
        $guzzle  = $this->createMock(ClientInterface::class);
        $guzzle->method('request')->willReturn($resp);

        $auth = $this->createMock(ProppitAuthenticatorInterface::class);
        $auth->method('authenticate')->willReturnArgument(0);

        return new GuzzleProppitHttpClient($guzzle, $this->config, $auth, new StructuredLogger(false));
    }

    // ── 401 → AuthException ───────────────────────────────────────────────────

    public function test_401_throws_AuthException(): void
    {
        $client = $this->makeClient(401, ['error' => 'Unauthorized']);

        $this->expectException(AuthException::class);
        $client->request('GET', '/proppit/CO/publishers/x');
    }

    public function test_401_does_not_throw_PublisherPermissionException(): void
    {
        $client = $this->makeClient(401, ['error' => 'Unauthorized']);

        try {
            $client->request('GET', '/proppit/CO/publishers/x');
            self::fail('Expected AuthException');
        } catch (AuthException $e) {
            self::assertNotInstanceOf(PublisherPermissionException::class, $e);
            self::assertSame(401, $e->getCode());
        }
    }

    // ── 403 publisher could not publish → PublisherPermissionException ────────

    public function test_403_publisher_could_not_publish_throws_PublisherPermissionException(): void
    {
        $client = $this->makeClient(403, [
            'status' => 403,
            'error'  => 'Publisher could not publish',
        ]);

        $this->expectException(PublisherPermissionException::class);
        $client->request('POST', '/proppit/CO/ads');
    }

    public function test_403_publisher_could_not_publish_is_also_PublisherNotReadyException(): void
    {
        $client = $this->makeClient(403, [
            'status' => 403,
            'error'  => 'Publisher could not publish',
        ]);

        try {
            $client->request('POST', '/proppit/CO/ads');
            self::fail('Expected exception');
        } catch (PublisherNotReadyException $e) {
            // PublisherNotReadyException extends PublisherPermissionException
            self::assertInstanceOf(PublisherPermissionException::class, $e);
        }
    }

    public function test_403_publisher_error_preserves_requestId(): void
    {
        $client = $this->makeClient(403, [
            'status'    => 403,
            'error'     => 'Publisher could not publish',
            'requestId' => 'req-abc123',
        ]);

        try {
            $client->request('POST', '/proppit/CO/ads');
            self::fail('Expected exception');
        } catch (PublisherPermissionException $e) {
            self::assertSame('req-abc123', $e->requestId());
        }
    }

    public function test_403_publisher_error_has_correct_statusCode(): void
    {
        $client = $this->makeClient(403, ['error' => 'Publisher could not publish']);

        try {
            $client->request('POST', '/proppit/CO/ads');
            self::fail('Expected exception');
        } catch (PublisherPermissionException $e) {
            self::assertSame(403, $e->statusCode);
        }
    }

    public function test_403_publisher_error_is_NOT_AuthException(): void
    {
        $client = $this->makeClient(403, ['error' => 'Publisher could not publish']);

        try {
            $client->request('POST', '/proppit/CO/ads');
            self::fail('Expected exception');
        } catch (PublisherPermissionException $e) {
            // Must NOT be caught as AuthException — it is a different condition
            self::assertNotInstanceOf(AuthException::class, $e);
        }
    }

    public function test_403_publisher_error_exposes_operationalHint(): void
    {
        $client = $this->makeClient(403, ['error' => 'Publisher could not publish']);

        try {
            $client->request('POST', '/proppit/CO/ads');
            self::fail('Expected exception');
        } catch (PublisherPermissionException $e) {
            self::assertNotEmpty($e->operationalHint());
        }
    }

    public function test_403_publisher_error_rawResponse_does_not_contain_secrets(): void
    {
        $client = $this->makeClient(403, [
            'error'         => 'Publisher could not publish',
            'client_secret' => 'should-be-redacted',
            'token'         => 'should-be-redacted',
        ]);

        try {
            $client->request('POST', '/proppit/CO/ads');
            self::fail('Expected exception');
        } catch (PublisherPermissionException $e) {
            self::assertStringNotContainsString('should-be-redacted', $e->rawResponse());
        }
    }

    // ── 403 generic → ForbiddenException (not PublisherPermissionException) ───

    public function test_403_generic_throws_ForbiddenException(): void
    {
        $client = $this->makeClient(403, ['error' => 'Access denied']);

        $this->expectException(ForbiddenException::class);
        $client->request('GET', '/proppit/CO/publishers');
    }

    public function test_403_generic_is_NOT_AuthException(): void
    {
        $client = $this->makeClient(403, ['error' => 'Access denied']);

        try {
            $client->request('GET', '/proppit/CO/publishers');
            self::fail('Expected ForbiddenException');
        } catch (ForbiddenException $e) {
            self::assertNotInstanceOf(AuthException::class, $e);
            self::assertSame(403, $e->statusCode);
        }
    }

    public function test_403_without_body_throws_ForbiddenException(): void
    {
        $client = $this->makeClient(403, []);

        $this->expectException(ForbiddenException::class);
        $client->request('GET', '/proppit/CO/publishers');
    }

    // ── 429 → RateLimitException ──────────────────────────────────────────────

    public function test_429_throws_RateLimitException(): void
    {
        // config retryAttempts=0 to avoid internal retry loop in test
        $config = ProppitConfig::fromArray([
            'base_url'       => 'https://real-time.proppit.com/api/v2',
            'client_id'      => 'u',
            'client_secret'  => 'p',
            'retry_attempts' => 0,
        ]);

        $json   = json_encode(['error' => 'Too many requests']);
        $resp   = new Response(429, ['Retry-After' => '30'], $json);
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')->willReturn($resp);

        $auth = $this->createMock(ProppitAuthenticatorInterface::class);
        $auth->method('authenticate')->willReturnArgument(0);

        $client = new GuzzleProppitHttpClient($guzzle, $config, $auth, new StructuredLogger(false));

        $this->expectException(RateLimitException::class);
        $client->request('POST', '/proppit/CO/ads');
    }

    // ── 500 → ApiException ────────────────────────────────────────────────────

    public function test_500_throws_ApiException(): void
    {
        $config = ProppitConfig::fromArray([
            'base_url'       => 'https://real-time.proppit.com/api/v2',
            'client_id'      => 'u',
            'client_secret'  => 'p',
            'retry_attempts' => 0,
        ]);

        $resp   = new Response(500, [], json_encode(['error' => 'Internal server error']));
        $guzzle = $this->createMock(ClientInterface::class);
        $guzzle->method('request')->willReturn($resp);

        $auth = $this->createMock(ProppitAuthenticatorInterface::class);
        $auth->method('authenticate')->willReturnArgument(0);

        $client = new GuzzleProppitHttpClient($guzzle, $config, $auth, new StructuredLogger(false));

        $this->expectException(ApiException::class);
        $client->request('POST', '/proppit/CO/ads');
    }

    // ── 200 → success ─────────────────────────────────────────────────────────

    public function test_200_returns_HttpResponse(): void
    {
        $client   = $this->makeClient(200, ['id' => 'pub-1', 'name' => 'Demo']);
        $response = $client->request('GET', '/proppit/CO/publishers/pub-1');

        self::assertSame(200, $response->statusCode);
        self::assertSame('pub-1', $response->json['id']);
    }
}
